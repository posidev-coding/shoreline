<?php

namespace App\Services\QuickBooks;

use App\Models\Client;
use App\Models\QuickBooksCustomer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Matches imported QuickBooks customers to clients that aren't linked yet.
 *
 * A customer's candidates are the unlinked clients with its email, phone, or display name. It
 * links by itself only when it has exactly one, no email or phone on the two sides disagree,
 * and no other customer wants that client. A wrong link would let QuickBooks overwrite the
 * wrong client once two-way sync arrives (M6), so anything less sure is left for the owner,
 * with the candidates offered as suggestions.
 */
final class CustomerMatcher
{
    public function __construct(private CustomerLinker $linker) {}

    /**
     * @return array{linked: int, review: int}
     */
    public function matchAll(string $realmId): array
    {
        /** @var array<int, array{customer: QuickBooksCustomer, client: Client}> $proposals */
        $proposals = [];

        // Collected first and linked after, since each link takes a customer out of the query.
        QuickBooksCustomer::query()->where('realm_id', $realmId)->needsReview()->lazyById()->each(function (QuickBooksCustomer $customer) use (&$proposals): void {
            $client = $this->sureMatch($customer);

            if ($client !== null) {
                $proposals[] = ['customer' => $customer, 'client' => $client];
            }
        });

        // Two customers wanting one client, like a couple sharing an email, is for the owner to sort out.
        $wanted = array_count_values(array_map(fn (array $proposal): int => $proposal['client']->getKey(), $proposals));
        $linked = 0;

        foreach ($proposals as ['customer' => $customer, 'client' => $client]) {
            if ($wanted[$client->getKey()] === 1) {
                $this->linker->link($customer, $client);
                $linked++;
            }
        }

        return [
            'linked' => $linked,
            'review' => QuickBooksCustomer::query()->where('realm_id', $realmId)->needsReview()->count(),
        ];
    }

    /**
     * @return Collection<int, Client>
     */
    public function suggestions(QuickBooksCustomer $customer): Collection
    {
        return $this->candidates($customer)->orderBy('display_name')->limit(5)->get();
    }

    private function sureMatch(QuickBooksCustomer $customer): ?Client
    {
        $candidates = $this->candidates($customer)->limit(2)->get();

        if ($candidates->count() !== 1) {
            return null;
        }

        $client = $candidates->sole();

        return $this->disagrees($customer, $client) ? null : $client;
    }

    /**
     * Whether both sides have an email and they differ, or both a phone and they differ.
     */
    private function disagrees(QuickBooksCustomer $customer, Client $client): bool
    {
        return (filled($customer->email) && filled($client->email) && $customer->email !== $client->email)
            || (filled($customer->phone) && filled($client->phone) && $customer->phone !== $client->phone);
    }

    /**
     * @return Builder<Client>
     */
    private function candidates(QuickBooksCustomer $customer): Builder
    {
        return Client::query()
            ->whereNull('qb_id')
            ->where(fn (Builder $query) => $query
                ->when($customer->email, fn (Builder $query, string $email) => $query->orWhereRaw('lower(email) = ?', [$email]))
                ->when($customer->phone, fn (Builder $query, string $phone) => $query->orWhere('phone', $phone))
                ->orWhereRaw('lower(display_name) = ?', [mb_strtolower(trim($customer->display_name))]));
    }
}
