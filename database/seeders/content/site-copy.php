<?php

// Seeded website copy (services, towns, FAQs, pages). The owner edits it in /admin afterwards;
// ContentSeeder only fills what doesn't exist yet. Town facts were checked against public sources.
/**
 * Seeded website copy for Shoreline Doors (M1).
 *
 * The owner edits all of this in Filament after seeding.
 */
return [

    'services' => [

        [
            'slug' => 'garage-door-installation',
            'name' => 'Garage Door Installation',
            'icon' => 'install',
            'headline' => 'Garage door installation, measured and set right',
            'summary' => 'New garage doors measured for your opening, picked for coastal weather, and installed to run smooth and stay balanced.',
            'body' => '<p>A new garage door changes how your house looks from the street and how it holds up when the weather turns. We measure your opening, help you choose a door that suits the house and the coast, and install it so it opens and closes the way it should.</p>'
                .'<h2>How an install goes</h2>'
                .'<ul>'
                .'<li><strong>Measure.</strong> We check the opening, the headroom above it, the room on each side, and the depth of the garage. Those numbers decide which doors and track setups will fit.</li>'
                .'<li><strong>Choose.</strong> We go over styles, materials, insulation, windows, and wind ratings, and what each one means for your house.</li>'
                .'<li><strong>Estimate.</strong> You get a written estimate before any work starts.</li>'
                .'<li><strong>Install.</strong> We take out the old door, set the new tracks, springs, and hardware, and balance the door so it stays put when it is halfway open.</li>'
                .'<li><strong>Walk-through.</strong> We show you the manual release, test the auto-reverse, and go over simple upkeep.</li>'
                .'</ul>'
                .'<h2>Choosing a door for the coast</h2>'
                .'<p>Salt air and humidity are hard on steel. Ask about the finish on the door, and about galvanized or stainless hardware if the door faces the water. Insulated doors are quieter and steadier on temperature, which helps if the garage doubles as a shop or a gym. Windows let in light, but they can change a door\'s wind rating, so we check that before you decide.</p>'
                .'<h2>Wind ratings and permits</h2>'
                .'<p>Depending on where your house sits, the code may call for a wind-rated door. What it takes depends on your address, the size of the door, and the code your town or county enforces. Your local building official has the final say on what is required and whether the job needs a permit. We will help you match the door to what they require.</p>'
                .'<h2>What to send with your quote request</h2>'
                .'<p>Send photos of the door from outside and inside, plus one of the area above the door where the springs and opener sit. Add the width and height of the opening if you have them. Let us know whether you want to keep your current opener or replace it.</p>',
            'seo_title' => 'Garage Door Installation in Morehead City, NC',
            'seo_description' => 'New garage door installation in Morehead City and across the Crystal Coast, with doors and hardware chosen for salt air, humidity, and wind.',
            'faqs' => [
                [
                    'question' => 'Do I need a wind-rated garage door?',
                    'answer' => 'It depends on your address, the size of the door, and the code your town or county enforces. Your local building official decides what is required, and we will help you find a door that meets it.',
                ],
                [
                    'question' => 'Can I keep my existing opener with a new door?',
                    'answer' => 'Often, yes, if the opener is in good shape and suited to the new door\'s size and weight. We will check it when we measure and tell you if it should be replaced.',
                ],
                [
                    'question' => 'What kind of door holds up best near the water?',
                    'answer' => 'There is no single answer for every house. The finish, the hardware, and how directly the door faces salt spray matter as much as the material, so we talk through the options for your spot.',
                ],
            ],
        ],

        [
            'slug' => 'garage-door-repair',
            'name' => 'Garage Door Repair',
            'icon' => 'repair',
            'headline' => 'Garage door repair for doors that stick, drag, or quit',
            'summary' => 'Doors that won\'t close, run loud, come off track, or reverse for no reason. We find the cause and fix it.',
            'body' => '<p>Most garage door trouble starts small. A roller wears out, a hinge cracks, a sensor gets bumped. Left alone, small problems strain the rest of the door and the opener. We find what is actually wrong and fix that.</p>'
                .'<h2>Common repairs</h2>'
                .'<ul>'
                .'<li>Doors that won\'t close, or close and then go right back up</li>'
                .'<li>Doors that are off track, crooked, or binding</li>'
                .'<li>Worn, rusted, or broken rollers and hinges</li>'
                .'<li>Bent track and loose brackets</li>'
                .'<li>Dented or cracked panels</li>'
                .'<li>Worn bottom seals that let in water, sand, and pests</li>'
                .'<li>Grinding, squealing, and banging</li>'
                .'<li>Broken springs and frayed lift cables</li>'
                .'</ul>'
                .'<h2>What salt air does</h2>'
                .'<p>Near the water, rust is the usual suspect. It seizes rollers, eats at hinges and brackets, and roughens the track until the door fights its way up. Sand tracked in on tires wears out the bottom seal. A repair visit is a good time to replace corroded parts before they fail and take something else with them.</p>'
                .'<h2>Before we get there</h2>'
                .'<ul>'
                .'<li>If the door is off track, crooked, or hanging at an angle, stop using it. Keep people, pets, and cars clear.</li>'
                .'<li>Don\'t run the opener against a stuck door. It can bend panels or burn out the motor.</li>'
                .'<li>If a spring looks broken, leave the door where it is. Springs hold a lot of tension and need the right tools.</li>'
                .'<li>If the door won\'t close, check that nothing is blocking the safety sensors near the floor and that the lenses are clean.</li>'
                .'</ul>'
                .'<h2>Help us figure it out</h2>'
                .'<p>When you request a quote, tell us what the door is doing and when it started. Did it happen all at once, or has it been getting worse? A few photos of the door, the track, and the hardware above the door help us show up with the right parts.</p>'
                .'<h2>Repair or replace?</h2>'
                .'<p>Most doors can be repaired. When a door is badly damaged, worn out all over, or not built for the wind where you live, replacing it can make more sense. We will tell you plainly which way we would go and why.</p>',
            'seo_title' => 'Garage Door Repair in Morehead City, NC',
            'seo_description' => 'Garage door repair across the Crystal Coast: off-track doors, worn rollers, noisy hardware, sensor trouble, and doors that won\'t close.',
            'faqs' => [
                [
                    'question' => 'My door closes, then goes right back up. What\'s wrong?',
                    'answer' => 'Often it is the safety sensors near the floor. Make sure nothing is in the way, wipe the lenses, and check that the two sensors still point at each other. If that doesn\'t fix it, the opener settings or the track may need a look.',
                ],
                [
                    'question' => 'Can you replace one damaged panel instead of the whole door?',
                    'answer' => 'Sometimes. It depends on whether matching panels are still made for your door and how much of the door is damaged. We will tell you which option makes more sense.',
                ],
                [
                    'question' => 'Is it safe to use a door that has come off its track?',
                    'answer' => 'No. A door that is off its track can twist or fall. Leave it where it is, keep everyone clear, and get in touch.',
                ],
            ],
        ],

        [
            'slug' => 'springs-and-cables',
            'name' => 'Springs & Cables',
            'icon' => 'spring',
            'headline' => 'Garage door spring and cable replacement, done safely',
            'summary' => 'Torsion and extension spring replacement, lift cable repair, and door balancing. Springs hold a lot of tension, so leave this one to us.',
            'body' => '<p>Springs do the heavy lifting on a garage door. The opener only guides it. When a spring breaks, the door suddenly weighs what it really weighs, which is often more than one person can safely lift.</p>'
                .'<h2>Signs of a broken spring or cable</h2>'
                .'<ul>'
                .'<li>A loud bang from the garage, sometimes when nobody was using the door</li>'
                .'<li>A gap in the coil of the spring above the door</li>'
                .'<li>The door lifts a few inches and stops, or the opener strains and hums</li>'
                .'<li>The door feels very heavy by hand, or sits crooked in the opening</li>'
                .'<li>A lift cable hanging loose, frayed, or off its drum</li>'
                .'</ul>'
                .'<h2>Torsion and extension springs</h2>'
                .'<p><strong>Torsion springs</strong> mount on a bar above the door and twist to lift it. <strong>Extension springs</strong> run alongside the tracks and stretch as the door closes. Both are under high tension. Extension springs should always have a safety cable running through them, so a broken spring can\'t whip across the garage. If yours don\'t, we can add them.</p>'
                .'<h2>Why we often replace springs in pairs</h2>'
                .'<p>If your door has two springs, they have done the same work for the same number of cycles. When one breaks, the other is often near the end of its life too. Replacing both keeps the door balanced and can save you a second visit.</p>'
                .'<h2>Salt air and springs</h2>'
                .'<p>Rust adds friction and weakens the steel, which shortens a spring\'s life. A light coat of garage door lubricant on the springs a couple of times a year helps. Keep an eye on the cables too. Rust can hide in the strands until a cable lets go.</p>'
                .'<h2>Please don\'t do this one yourself</h2>'
                .'<p>Winding or unwinding a torsion spring takes the right tools and training, and a slip can cause serious injury. If a spring is broken, leave the door where it is and don\'t run the opener. If the door is stuck open, don\'t pull the emergency release. Without the spring, the door can drop fast.</p>',
            'seo_title' => 'Garage Door Spring Repair in Morehead City, NC',
            'seo_description' => 'Broken garage door spring or frayed cable on the Crystal Coast? We replace torsion and extension springs and lift cables and rebalance the door.',
            'faqs' => [
                [
                    'question' => 'Can I still open my door with a broken spring?',
                    'answer' => 'Please don\'t try. Without the spring the door is very heavy and can drop fast, and running the opener can damage it. Leave the door where it is and get in touch.',
                ],
                [
                    'question' => 'How long do garage door springs last?',
                    'answer' => 'It depends on the spring and how often the door runs. Springs are made for a set number of open-and-close cycles, and rust near the coast can shorten that.',
                ],
                [
                    'question' => 'Should both springs be replaced at the same time?',
                    'answer' => 'Usually. Two springs on the same door wear at the same rate, so when one breaks the other is often close behind. Replacing both keeps the door balanced.',
                ],
            ],
        ],

        [
            'slug' => 'openers-and-smart-controls',
            'name' => 'Openers & Smart Controls',
            'icon' => 'opener',
            'headline' => 'Garage door openers, keypads, and smart controls',
            'summary' => 'Opener installs and repairs, remotes and keypads, battery backup, and smart controls that let you check and close your door from your phone.',
            'body' => '<p>An opener should lift your door quietly, stop when something is in the way, and keep working when the weather is bad. We install new openers, repair the ones you have, and set up the remotes, keypads, and smart controls that go with them.</p>'
                .'<h2>Choosing an opener</h2>'
                .'<ul>'
                .'<li><strong>Belt drive</strong> runs quiet, which matters if there is a bedroom over the garage.</li>'
                .'<li><strong>Chain drive</strong> is simple and strong.</li>'
                .'<li><strong>Wall-mount openers</strong> sit beside the door instead of hanging from the ceiling. They free up overhead space, which helps with high ceilings, low headroom, or overhead storage.</li>'
                .'<li><strong>Battery backup</strong> keeps the door running for a while when the power is out.</li>'
                .'</ul>'
                .'<h2>Smart controls</h2>'
                .'<p>Smart openers and add-on controllers let you check the door from your phone, close it from down the road, and get an alert when it is left open. Many let you give family or guests their own access. Compatibility varies, so we check what works with your opener and your Wi-Fi before you buy anything.</p>'
                .'<h2>Safety sensors and the auto-reverse</h2>'
                .'<p>The two small sensors near the floor stop and reverse the door if something crosses the beam. The opener should also reverse if the door touches something on the way down. Test it once a month: lay a 2x4 flat on the floor under the middle of the door and close it. The door should reverse when it meets the board. If it doesn\'t, stop using the opener and get it looked at.</p>'
                .'<h2>Coastal wear</h2>'
                .'<p>Humidity and salt air get into rails, chains, and circuit boards. Rust on the rail, a door that stops partway, or a remote that only works up close can all point to wear. Sometimes it is a simple fix. Sometimes the opener is near the end, and we will tell you which.</p>'
                .'<h2>Remotes and keypads</h2>'
                .'<p>Lost a remote, moved into a house with codes you don\'t know, or want a keypad for the kids? We can set up new remotes and keypads, clear out old codes, and help with the buttons built into many cars.</p>',
            'seo_title' => 'Garage Door Openers in Morehead City, NC',
            'seo_description' => 'Garage door opener installs and repairs on the Crystal Coast: belt and chain drives, wall-mount openers, battery backup, keypads, and smart controls.',
            'faqs' => [
                [
                    'question' => 'Can my current opener be made smart?',
                    'answer' => 'Often, yes. Many openers work with an add-on smart controller. We check your opener\'s model first, since compatibility varies.',
                ],
                [
                    'question' => 'Will my opener work when the power goes out?',
                    'answer' => 'Only if it has a battery backup. Without one, you can use the manual release to open the door by hand, as long as the springs are in good shape.',
                ],
                [
                    'question' => 'Can you program my remotes, keypad, and car buttons?',
                    'answer' => 'We can set up remotes and keypads and clear out old codes. Many cars have built-in buttons that can be paired with your opener, and we can help with those too.',
                ],
            ],
        ],

        [
            'slug' => 'hurricane-wind-rated-doors',
            'name' => 'Hurricane & Wind-Rated Doors',
            'icon' => 'wind',
            'headline' => 'Wind-rated garage doors for hurricane country',
            'summary' => 'Wind-rated and impact-rated garage doors, reinforcement, and storm-season checkups for the largest opening in most houses.',
            'body' => '<p>On most houses, the garage door is the largest opening. If it gives way in a storm, wind rushes into the garage and pushes up on the roof and out on the walls. That is how a failed door turns into a much bigger repair. A door built and installed to handle the wind keeps that pressure outside.</p>'
                .'<h2>Wind-rated and impact-rated</h2>'
                .'<p><strong>Wind-rated</strong> doors have been tested to hold up against a set wind pressure, both pushing in and pulling out. <strong>Impact-rated</strong> doors have also been tested against flying debris and repeated wind pressure. The Door and Access Systems Manufacturers Association (DASMA) publishes the test methods used for many garage doors: ANSI/DASMA 108 for wind pressure and ANSI/DASMA 115 for debris impact.</p>'
                .'<h2>What we do</h2>'
                .'<ul>'
                .'<li><strong>Door checks.</strong> We find your door\'s label, read the rating, and look over the track, brackets, hinges, struts, and fasteners.</li>'
                .'<li><strong>New rated doors.</strong> We help you choose a wind-rated or impact-rated door that meets what your address requires, and install it the way the manufacturer\'s drawing for that rating calls for. A rating only holds if the door is installed that way.</li>'
                .'<li><strong>Reinforcement.</strong> Some doors can take added struts or a bracing kit made for that model. We tell you what is available for yours.</li>'
                .'<li><strong>Pre-season tune-ups.</strong> Rusty hardware and loose fasteners are weak points in high wind. Replace them before storm season, not during it.</li>'
                .'</ul>'
                .'<h2>Read the label</h2>'
                .'<p>A wind-rated door should carry a permanent label from the manufacturer. It lists the model, the design pressure the door is rated for (one number for wind pushing in, one for wind pulling out), the installation drawing, and the test standard. The rating is in pounds per square foot, not miles per hour. If you can\'t find a label, the door may not be rated. If you do find one, leave it on.</p>'
                .'<h2>What your address requires</h2>'
                .'<p>The pressure a door has to handle depends on where the house sits, how exposed it is, the height of the roof, and the size of the door. Your town or county building official decides what is required and whether the work needs a permit. Ask them before you buy, and we will help you match the door to their answer.</p>'
                .'<p>For more on getting ready before a storm, see our Hurricane-Ready page.</p>',
            'seo_title' => 'Hurricane & Wind-Rated Garage Doors, Morehead City',
            'seo_description' => 'Wind-rated and impact-rated garage doors on the Crystal Coast. We help you read your door\'s wind label, reinforce what you have, or upgrade.',
            'faqs' => [
                [
                    'question' => 'Will a wind-rated door lower my insurance premium?',
                    'answer' => 'Some insurers offer discounts for wind-resistant features, but it depends on your insurer and your policy. Ask your agent before you buy, and keep a photo of the door\'s label for your records.',
                ],
                [
                    'question' => 'Can my current door be reinforced?',
                    'answer' => 'Sometimes. Some manufacturers make reinforcement kits for specific models. Whether that gives your door a rating depends on what the manufacturer tested, so we check for your model.',
                ],
                [
                    'question' => 'How do I know if my door is wind-rated?',
                    'answer' => 'Check the inside of the door for the manufacturer\'s label. It shows the design pressure ratings in pounds per square foot. No label usually means no rating, but we can help you check.',
                ],
            ],
        ],

        [
            'slug' => 'handyman-home-repairs',
            'name' => 'Handyman & Home Repairs',
            'icon' => 'toolbox',
            'headline' => 'Handyman help for the odd jobs around the house',
            'summary' => 'Sticking doors, soft trim, loose deck boards, shelving, and the rest of the list. Small repairs and odd jobs around the house.',
            'body' => '<p>Most houses have a list. The door that sticks every August, the trim that went soft, the deck board that moves when you step on it. We take on the odd jobs around the house the same way we handle garage doors: look it over, explain what we see, and do the work right.</p>'
                .'<h2>The kind of work we take on</h2>'
                .'<ul>'
                .'<li>Entry, interior, and storm doors: hanging, adjusting, hardware, and weatherstripping</li>'
                .'<li>Trim, siding, and fascia boards with rot or damage</li>'
                .'<li>Deck boards, steps, and railings</li>'
                .'<li>Drywall patches and small repairs</li>'
                .'<li>Caulking and sealing around windows, doors, and tubs</li>'
                .'<li>Shelving, garage storage, and mounting</li>'
                .'<li>Fence and gate repairs</li>'
                .'<li>Small repairs after a storm</li>'
                .'</ul>'
                .'<h2>Rentals and second homes</h2>'
                .'<p>If you own a rental or a second home, send us a punch list and the access details, and we will work through it. Tell us your changeover day and any rules about when work can happen, and we will plan around them. We send updates and photos as we go, so you can see where things stand without driving over.</p>'
                .'<h2>Where we draw the line</h2>'
                .'<p>Some jobs call for a licensed electrician, plumber, HVAC technician, or roofer. When a job needs one of them, we will say so up front. We focus on repairs and odd jobs, not full remodels. If you are not sure where your job falls, ask.</p>'
                .'<h2>Bundle the small stuff</h2>'
                .'<p>Several small jobs in one visit usually make more sense than a trip for each. Put everything on one quote request with a photo of each item, and we will sort out a plan. If we are already there for the garage door, mention the rest of the list.</p>',
            'seo_title' => 'Handyman Services in Morehead City, NC',
            'seo_description' => 'Handyman and home repairs on the Crystal Coast: doors, trim, decks, drywall, shelving, caulking, and rental punch lists. Send us your list.',
            'faqs' => [
                [
                    'question' => 'What kinds of odd jobs do you take on?',
                    'answer' => 'Repairs and small projects around the house: doors, trim, decks, drywall, caulking, shelving, and similar work. If a job needs a licensed trade like electrical or plumbing, we will tell you.',
                ],
                [
                    'question' => 'Can you handle several small jobs in one visit?',
                    'answer' => 'Yes. Send them all in one quote request with photos, and we will plan the visit around the list.',
                ],
                [
                    'question' => 'Do you work on rental properties?',
                    'answer' => 'Yes. We work with owners and property managers, and we can plan around guest changeovers. Send the access details and your changeover day with your request.',
                ],
            ],
        ],

    ],

    'service_areas' => [

        [
            'slug' => 'morehead-city',
            'name' => 'Morehead City',
            'county' => 'carteret',
            'intro' => '<p>Morehead City is home base for Shoreline Doors. It is a working port town on a peninsula, with Bogue Sound to the south, the Newport River to the north and east, and the Port of Morehead City right on the water. A lot of houses here are within reach of salt air and steady wind off the sound.</p>'
                .'<p>For a garage door, that means rust on springs, rollers, and hinges sooner than you would expect, and track that roughens as it corrodes. Doors that face open water take more wind than doors tucked behind the house. Humidity works on openers, wood trim, and doors that start to stick every summer.</p>'
                .'<p>We handle new doors, repairs, springs and cables, openers, and the odd jobs around the house anywhere in town. If your door is slow, loud, or rusting at the hinges, send us a few photos and we will tell you what we see.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Morehead City, NC',
            'seo_description' => 'Garage door repair, installation, springs, openers, and handyman work in Morehead City, NC, from a local business based in town.',
        ],

        [
            'slug' => 'beaufort',
            'name' => 'Beaufort',
            'county' => 'carteret',
            'intro' => '<p>Beaufort is the Carteret County seat and the third-oldest town in North Carolina, just across the Newport River from Morehead City. Its historic district is on the National Register of Historic Places, and the town\'s Historic Preservation Commission reviews changes to the outside of buildings in the district.</p>'
                .'<p>If your house is in the district, a new garage door or other exterior change may need the commission\'s approval before work starts. Check with the town first. We will work from what they approve, help you find a door style that suits an older house, and match any wind rating your building official requires.</p>'
                .'<p>Beaufort sits right on the water, so salt air is part of life here. Older houses also come with doors that have settled, swollen, or been painted shut, and trim that needs a careful hand. Those are the odd jobs we take on, along with garage door repairs, springs, and openers.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Beaufort, NC',
            'seo_description' => 'Garage door repair, installation, and handyman work in Beaufort, NC, including doors for older homes in and around the historic district.',
        ],

        [
            'slug' => 'atlantic-beach',
            'name' => 'Atlantic Beach',
            'county' => 'carteret',
            'intro' => '<p>Atlantic Beach sits on the eastern end of Bogue Banks, across the Atlantic Beach Causeway from Morehead City, with Fort Macon State Park at the tip of the island. Between the ocean on one side and Bogue Sound on the other, there is no hiding from salt spray, sand, or wind.</p>'
                .'<p>That is hard on a garage door. Salt eats at springs, hinges, and rollers. Sand gets into the track and wears out the bottom seal. Wind off the ocean puts real load on the door, which makes a rated door and solid hardware worth the attention. If your house is raised, the garage door at ground level takes the brunt of whatever a storm brings.</p>'
                .'<p>If your place is a rental or a second home, we can work with you or your property manager, plan around changeover days, and take care of the small repairs that pile up between guests.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Atlantic Beach, NC',
            'seo_description' => 'Garage door repair, wind-rated doors, springs, openers, and handyman work in Atlantic Beach, NC, for homes, rentals, and second homes.',
        ],

        [
            'slug' => 'emerald-isle',
            'name' => 'Emerald Isle',
            'county' => 'carteret',
            'intro' => '<p>Emerald Isle covers the western end of Bogue Banks, out to Bogue Inlet, and reaches the mainland at Cape Carteret by way of NC 58 and the B. Cameron Langston Bridge. It is a family beach town, and its oceanfront is lined with houses, duplexes, and condos.</p>'
                .'<p>Houses here get salt spray and sand off the ocean and wind across the sound. Garage doors closest to the beach usually show it first: rusted hinges and rollers, stiff track, and bottom seals worn down by sand. If your house sits up high with the garage underneath, that door is your first line against wind and blowing debris.</p>'
                .'<p>If you rent your place out, send us the access details and your changeover day, and we will plan the work around guests. We handle repairs, new and wind-rated doors, springs and cables, openers, and the punch-list jobs that come up between seasons.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Emerald Isle, NC',
            'seo_description' => 'Garage door repair, wind-rated doors, openers, and handyman work in Emerald Isle, NC, for year-round homes and vacation rentals.',
        ],

        [
            'slug' => 'newport',
            'name' => 'Newport',
            'county' => 'carteret',
            'intro' => '<p>Newport is a mostly residential town on US 70 between Morehead City and Havelock, along the Newport River and at the edge of the Croatan National Forest. Marine Corps Air Station Cherry Point is a short drive up the highway.</p>'
                .'<p>Newport sits back from the ocean, so it is spared the worst of the salt spray that hits Bogue Banks. Humidity is another story. Steel hardware still rusts, wooden doors and trim swell, and garage doors that run every day wear out rollers, hinges, and springs. When a storm knocks the power out, an opener with battery backup or a working manual release makes a big difference.</p>'
                .'<p>We handle garage door repairs and installs, springs and cables, openers and smart controls, and the odd jobs around the house. If you are getting a house ready to sell or rent, we can work through the list of small repairs too.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Newport, NC',
            'seo_description' => 'Garage door repair, springs, openers, smart controls, and handyman work in Newport, NC, between Morehead City and Havelock.',
        ],

        [
            'slug' => 'pine-knoll-shores',
            'name' => 'Pine Knoll Shores',
            'county' => 'carteret',
            'intro' => '<p>Pine Knoll Shores sits on Bogue Banks between Atlantic Beach and Indian Beach. Trees set it apart. The Theodore Roosevelt State Natural Area protects the last intact maritime forest on the island, and the North Carolina Aquarium at Pine Knoll Shores sits within it.</p>'
                .'<p>Shade and shelter help with wind, but they bring their own wear. Damp spots stay damp, so hardware rusts and doors and trim can grow mildew. Leaves and other debris collect along the bottom seal and in the track. Salt still comes in off the ocean and the sound, so springs, rollers, and hinges need the same attention they do anywhere on the island.</p>'
                .'<p>We repair and replace garage doors, swap springs and cables, install openers and smart controls, and take on the odd jobs around the house. If you are only here part of the year, a smart controller lets you check the garage door from wherever you are.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Pine Knoll Shores',
            'seo_description' => 'Garage door repair, installation, springs, openers, and handyman work in Pine Knoll Shores, NC, on Bogue Banks.',
        ],

        [
            'slug' => 'cape-carteret',
            'name' => 'Cape Carteret',
            'county' => 'carteret',
            'intro' => '<p>Cape Carteret is on the mainland side of Bogue Sound, across the water from Emerald Isle and tied to it by the B. Cameron Langston Bridge. Cedar Point is next door to the west, and Pettiford Creek and the Croatan National Forest run along the north side of town.</p>'
                .'<p>Being on the mainland takes some of the edge off the salt, but homes along the sound still catch wind across open water, and humidity is constant everywhere. That shows up as rusty hinges and rollers, openers that slow down, springs that wear out sooner, and doors and trim that stick or swell.</p>'
                .'<p>We repair and install garage doors, replace springs and cables, set up openers, keypads, and smart controls, and handle the odd jobs on your list. If you want a wind-rated door, we will help you find one that meets what your building official requires.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Cape Carteret, NC',
            'seo_description' => 'Garage door repair, installation, springs, openers, and handyman work in Cape Carteret, NC, on the mainland side of Bogue Sound.',
        ],

        [
            'slug' => 'swansboro',
            'name' => 'Swansboro',
            'county' => 'onslow',
            'intro' => '<p>Swansboro sits on the White Oak River in Onslow County, across the Intracoastal Waterway from Hammocks Beach State Park. People sometimes call it the Friendly City by the Sea. Its historic district, on the National Register of Historic Places, covers much of the old downtown and the streets around it.</p>'
                .'<p>In the historic district, the town requires a Certificate of Appropriateness from its Historic Preservation Commission for exterior changes. If your house is in the district, check with the town before you change the garage door or anything else on the outside. Repairs that match what is already there may not need one, but ask first.</p>'
                .'<p>River and waterway homes deal with humidity, salt, and wind, and garage doors show it in rusty hardware and worn seals. We handle repairs, new and wind-rated doors, springs, openers, and the odd jobs around the house.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Swansboro, NC',
            'seo_description' => 'Garage door repair, installation, springs, openers, and handyman work in Swansboro, NC, including homes in and near the historic district.',
        ],

        [
            'slug' => 'havelock',
            'name' => 'Havelock',
            'county' => 'craven',
            'intro' => '<p>Havelock is in southern Craven County and is home to Marine Corps Air Station Cherry Point, most of which lies inside the city limits. US 70 runs through the middle of town, and the city reaches north to the Neuse River.</p>'
                .'<p>Havelock is set back from the ocean, so salt spray is less of a problem than it is on the beach. Humidity and storms still reach it. That means rusty springs and rollers over time, openers that wear out, and power outages when a storm comes through, which is when battery backup earns its keep.</p>'
                .'<p>If you are getting a house ready to sell or rent before a move, we can take care of the garage door and the small repairs on the walk-through list. If you are settling in, we can set up new remotes and keypad codes so you know who can open your garage. We handle repairs, installs, springs, openers, and odd jobs.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Havelock, NC',
            'seo_description' => 'Garage door repair, installation, springs, openers, and handyman work in Havelock, NC, home of MCAS Cherry Point.',
        ],

        [
            'slug' => 'indian-beach',
            'name' => 'Indian Beach',
            'county' => 'carteret',
            'intro' => '<p>Indian Beach is a small town on Bogue Banks between Pine Knoll Shores and Emerald Isle. It comes in two sections, with the unincorporated community of Salter Path in between, and NC 58 is the only main road through. We work in Salter Path too.</p>'
                .'<p>With the ocean on one side and Bogue Sound on the other, salt and sand are constant. Garage doors here need hardware that can take it, regular attention to rollers, hinges, and springs, and bottom seals that keep out water and sand. Wind off the water makes a rated door and well-fastened track worth checking before storm season.</p>'
                .'<p>If your home is in a condo building or a community with an association, check the rules on door styles and colors, and who is responsible for the door, before you replace it. If you rent your place out, we can plan around changeover days. We handle repairs, new doors, springs, openers, and odd jobs.</p>',
            'seo_title' => 'Garage Door Repair & Installation in Indian Beach, NC',
            'seo_description' => 'Garage door repair, wind-rated doors, springs, openers, and handyman work in Indian Beach and Salter Path, NC, on Bogue Banks.',
        ],

    ],

    'faqs' => [
        [
            'question' => 'How do I schedule work?',
            'answer' => 'Give us a call, or send a quote request with a few photos. We will follow up to talk it through, send an estimate, and set a time that works for you.',
        ],
        [
            'question' => 'What areas do you serve?',
            'answer' => 'We are based in Morehead City and work across Carteret County, including Beaufort, Atlantic Beach, Pine Knoll Shores, Indian Beach, Salter Path, Emerald Isle, Cape Carteret, and Newport. We also serve Swansboro in Onslow County and Havelock in Craven County. If you are just outside those areas, ask.',
        ],
        [
            'question' => 'My garage door won\'t close. What should I do?',
            'answer' => 'Check that nothing is blocking the safety sensors near the floor and wipe their lenses. If your wall button has a lock setting, make sure it is off. If the door is crooked, off its track, or a spring looks broken, stop using it and keep clear. Don\'t keep pressing the button or force the door by hand.',
        ],
        [
            'question' => 'Can I replace a garage door spring myself?',
            'answer' => 'We strongly recommend against it. Garage door springs hold a lot of tension, and winding or releasing them without the right tools and training can cause serious injury. If a spring breaks, leave the door where it is and get in touch.',
        ],
        [
            'question' => 'How do I take care of a garage door in salt air?',
            'answer' => 'Rinse the outside of the door with fresh water now and then, especially after a storm or a windy stretch. Put garage door lubricant on the hinges, rollers, and springs a couple of times a year, and watch for rust on cables and brackets. Test the auto-reverse once a month by closing the door on a 2x4 laid flat on the floor.',
        ],
        [
            'question' => 'Can you make my garage door smart?',
            'answer' => 'Often, yes. Many openers can take an add-on smart controller, and new openers often have it built in. Either way, you can check and close the door from your phone and get an alert when it is left open.',
        ],
        [
            'question' => 'Will insurance cover storm damage to my garage door?',
            'answer' => 'That depends on your policy, so your insurer is the one to ask. We can look at the damage, take photos, and give you a written estimate for the repair or replacement to share with them.',
        ],
        [
            'question' => 'What odd jobs do you take on besides garage doors?',
            'answer' => 'Repairs and small projects around the house, like sticking doors, trim and deck repairs, drywall patches, caulking, shelving, and rental punch lists. If a job needs a licensed electrician, plumber, HVAC technician, or roofer, we will tell you up front.',
        ],
    ],

    'pages' => [

        'about' => [
            'title' => 'About Shoreline Doors',
            'intro' => 'A local garage door and handyman business based in Morehead City, North Carolina.',
            'body' => '<p>Shoreline Doors is a local business based in Morehead City. We install and repair garage doors, and we take care of the odd jobs around the house.</p>'
                .'<p>We work across Carteret County, from Beaufort and Newport to Cape Carteret and the towns on Bogue Banks. We also serve Swansboro in Onslow County and Havelock in Craven County.</p>'
                .'<h2>How we work</h2>'
                .'<ul>'
                .'<li><strong>We tell you what we see.</strong> If a repair makes more sense than a new door, we will say so.</li>'
                .'<li><strong>We plan for the coast.</strong> Salt air, humidity, sand, and storms shape the doors, parts, and hardware we recommend.</li>'
                .'<li><strong>We put it in writing.</strong> You get a written estimate before work starts, and we confirm the details on site.</li>'
                .'</ul>'
                .'<h2>Garage doors and the rest of the list</h2>'
                .'<p>Garage doors are the core of what we do: new doors, repairs, springs and cables, openers, and wind-rated doors for hurricane season. Since we are already at the house, we also take on the jobs that tend to pile up, like sticking doors, soft trim, loose deck boards, and shelving. Send us the whole list.</p>'
                .'<p>Questions about a job? Request a quote with a few photos, or email hello@shorelinedoors.com.</p>',
        ],

        'hurricane-ready' => [
            'title' => 'Is Your Garage Door Hurricane-Ready?',
            'intro' => 'What keeps a garage door in place when the wind picks up, and what to check before storm season.',
            'body' => '<p>On most houses, the garage door is the largest opening. When a garage door fails, wind rushes into the garage and pressure builds inside the house. That pressure pushes up on the roof and out on the walls, and it can start a chain of damage that goes well beyond the door.</p>'
                .'<h2>How wind works on a door</h2>'
                .'<p>Depending on which way the wind blows, it can push in on a garage door or pull out on it, and a door has to resist both. Doors usually fail in a few ways: weak track buckles, a poorly attached door pulls loose from the building, or flying debris punches through a panel or a window.</p>'
                .'<h2>Wind-rated and impact-rated doors</h2>'
                .'<p>A <strong>wind-rated</strong> door has been tested to hold up against a set wind pressure. An <strong>impact-rated</strong> door has also been tested against flying debris and repeated pressure. The Door and Access Systems Manufacturers Association (DASMA) publishes two test standards used for many garage doors:</p>'
                .'<ul>'
                .'<li><strong>ANSI/DASMA 108</strong> tests a door\'s structural performance under uniform static air pressure, the steady push and pull of wind.</li>'
                .'<li><strong>ANSI/DASMA 115</strong> tests a door\'s performance under missile impact and cyclic wind pressure, meaning flying debris followed by repeated gusts.</li>'
                .'</ul>'
                .'<h2>Find your door\'s label</h2>'
                .'<p>A wind-rated door should carry a permanent label from its manufacturer. Check the inside of the door. The label names the manufacturer and model, the positive and negative design pressure (wind pushing in and pulling out), the installation drawing, and the test standard. Ratings are in pounds per square foot, not miles per hour. A door without a wind rating does not get a label, so no label is a sign to look closer. Photograph it for your records and leave it on the door.</p>'
                .'<h2>What your address requires</h2>'
                .'<p>The pressure a door needs to handle depends on the wind speed used for your area, how exposed the house is, the roof height, and the size of the door. We won\'t guess at that for you. Your local building official or permit office decides what the code requires at your address. Call them before you buy a new door.</p>'
                .'<h2>Before storm season</h2>'
                .'<p>Hurricane season runs from June 1 to November 30. Before it starts:</p>'
                .'<ul>'
                .'<li>Look over the track, brackets, and hinges for rust, cracks, and loose or missing fasteners.</li>'
                .'<li>Check that any reinforcing struts are in place and tight.</li>'
                .'<li>Replace worn rollers and a bottom seal that no longer meets the floor.</li>'
                .'<li>Learn how your manual release works in case the power goes out.</li>'
                .'<li>If your door has slide locks, make sure they still slide into the track.</li>'
                .'</ul>'
                .'<h2>What not to do</h2>'
                .'<ul>'
                .'<li>Don\'t brace a door at the last minute with whatever lumber is in the garage, especially if you don\'t know its rating. A brace that isn\'t designed for your door can crack panels or tear out the track when the wind loads it. If you want bracing, get a system made for your door and set it up well before a storm.</li>'
                .'<li>Don\'t run the opener against a braced or locked door. Unplug it first.</li>'
                .'<li>Don\'t remove struts or swap hardware on a rated door. Its rating depends on being installed the way the manufacturer tested it.</li>'
                .'<li>Don\'t assume a newer door is rated. Check the label.</li>'
                .'</ul>'
                .'<h2>Check your door</h2>'
                .'<p>Take the quick check below. It asks a few questions about your door and, if your door could use a closer look, starts a quote request with your answers filled in.</p>',
        ],

        'privacy' => [
            'title' => 'Privacy Policy',
            'intro' => 'How Shoreline Doors collects, uses, and protects your information.',
            'body' => '<!-- DRAFT: have this reviewed before relying on it. -->'
                .'<p><strong>Last updated:</strong> {{ last_updated }}</p>'
                .'<p>Shoreline Doors ("we," "us," or "our") is a garage door and home repair business in Morehead City, North Carolina. This policy explains what information we collect through our website, our client portal, and our work with you, how we use it, and the choices you have.</p>'

                .'<h2>Information we collect</h2>'
                .'<p><strong>Information you give us.</strong> When you request a quote, contact us, or work with us, we collect:</p>'
                .'<ul>'
                .'<li>Your name, email address, phone number, and the address of the property</li>'
                .'<li>Project details, such as the service you need, the size and style of your door, and what is going wrong</li>'
                .'<li>Photos and files you upload</li>'
                .'<li>Property details you choose to share, such as gate or lockbox codes and the name of an on-site or rental-management contact</li>'
                .'<li>Messages you send us through the client portal or by email</li>'
                .'<li>Your approval of an estimate, including the name you type, the date and time, and your IP address and browser details</li>'
                .'<li>With a quote request: the date and time, your IP address, whether you agreed to texts or reminders, and how you found us (the first page you visited, the site that sent you there, and any campaign tags in that link)</li>'
                .'</ul>'
                .'<p><strong>Information from others.</strong> A property manager, landlord, or family member may give us your contact details so we can reach you about work at a property.</p>'
                .'<p><strong>Information collected automatically.</strong> Like most websites, our servers record basic technical information when you visit, such as your IP address, browser type, and the pages you request. We use it to run and secure the site.</p>'

                .'<h2>How we use your information</h2>'
                .'<ul>'
                .'<li>To respond to your request, prepare estimates, schedule and do the work, and send invoices</li>'
                .'<li>To run your client portal account</li>'
                .'<li>To send the emails described below</li>'
                .'<li>To keep our records, including accounting and tax records</li>'
                .'<li>To protect our website and forms from spam and abuse</li>'
                .'</ul>'
                .'<p>We show photos of your project on our website or elsewhere only with your permission. When we do, we identify the town, never the street address, and we remove location data from the photo files.</p>'

                .'<h2>Client portal</h2>'
                .'<p>Clients we invite can use our client portal to see job updates, photos, documents, estimates, and invoices, to message us, and to approve estimates and pay online. The portal does not use passwords. To sign in, you enter your email address and we send you a one-time code and a sign-in link that expire after a short time. Anyone with access to your email can sign in to your portal account, so keep your email account secure.</p>'
                .'<p>If you are a property manager or share an account with others, the people on that account can see the projects, documents, and messages on it.</p>'

                .'<h2>Emails we send</h2>'
                .'<ul>'
                .'<li><strong>Service emails:</strong> confirmations of your quote request, sign-in codes, estimates, invoices, scheduling notices, portal messages, and job updates. These are part of providing our service.</li>'
                .'<li><strong>Review requests:</strong> after a job is done, we may email you to ask about your experience. Whether you leave a review is up to you.</li>'
                .'<li><strong>Reminders:</strong> optional seasonal and maintenance reminders, such as a note to check your door before hurricane season.</li>'
                .'</ul>'
                .'<p>Review requests and reminders include an unsubscribe link. You can also email us to stop them.</p>'

                .'<h2>QuickBooks Online and Intuit</h2>'
                .'<p>We use QuickBooks Online, a product of Intuit Inc., for estimates, invoices, payments, and our books. To make that work:</p>'
                .'<ul>'
                .'<li>Your contact and billing details, such as your name, company name, email, phone, and billing address, are synced between our website and our QuickBooks Online account. Estimates, invoices, and payment status are synced too.</li>'
                .'<li>Online payments are processed by Intuit through QuickBooks Payments. When you pay online, you enter your card or bank details on Intuit\'s payment page. That information goes to Intuit and never touches our servers, and we do not store it.</li>'
                .'<li>We use the data we receive from Intuit\'s APIs only to provide our services to you, such as showing your estimates, invoices, and balance in the portal. We do not sell it, and we do not use it for advertising.</li>'
                .'<li>If you ask us to, or if our QuickBooks connection to this website is disconnected, we delete or disconnect the data this website received from Intuit\'s APIs, except where we must keep records for legal, tax, or accounting reasons.</li>'
                .'</ul>'
                .'<p>Intuit\'s handling of your information is covered by Intuit\'s own privacy statement.</p>'

                .'<h2>Cookies</h2>'
                .'<p>Our public pages, such as our services, service area, and FAQ pages, do not set cookies. When you use a form, such as the quote request, or sign in to the client portal, we set cookies that are needed to keep you signed in and to protect forms from forgery. We do not use advertising or tracking cookies.</p>'
                .'<p>Our pages do keep one note in your browser\'s local storage: the first page you visited, the site that sent you there, and any campaign tags in that link. It is sent to us only if you send a quote request, so we know how you found us, and we stop using it after 90 days.</p>'

                .'<h2>Spam protection and analytics</h2>'
                .'<p><strong>Cloudflare Turnstile.</strong> Our forms use Cloudflare Turnstile to tell people from bots. Turnstile checks technical signals from your browser and connection, such as your IP address and browser details. Cloudflare states that it uses these signals to detect and block bots, not to identify, profile, or target individuals.</p>'
                .'<p><strong>Cloudflare Web Analytics.</strong> We use Cloudflare Web Analytics to see how many people visit our pages and which pages they use. It is a privacy-focused tool. According to Cloudflare, it does not use cookies or local storage to track visitors and does not fingerprint them.</p>'

                .'<h2>Who we share information with</h2>'
                .'<p>We do not sell your personal information. We share it only as needed to run our business and provide our services:</p>'
                .'<ul>'
                .'<li><strong>Laravel Cloud</strong> hosts our website and database.</li>'
                .'<li><strong>Cloudflare</strong> stores uploaded photos and files (Cloudflare R2), delivers our emails, and provides Turnstile and Web Analytics.</li>'
                .'<li><strong>Intuit</strong> provides QuickBooks Online and QuickBooks Payments, as described above.</li>'
                .'<li><strong>Suppliers and manufacturers</strong> may receive details such as door sizes or photos when we need them to order parts or check a warranty.</li>'
                .'<li><strong>Others when required.</strong> We may share information when the law requires it, to protect our rights or someone\'s safety, or as part of a sale or transfer of our business.</li>'
                .'</ul>'

                .'<h2>How long we keep information</h2>'
                .'<p>We keep client, project, and billing records for as long as we need them to serve you and to meet legal, tax, and accounting requirements. Quote requests that do not turn into work are kept only as long as they are useful for following up. Unfinished uploads are deleted automatically. When we no longer need information, we delete it or remove the details that identify you.</p>'

                .'<h2>How we protect information</h2>'
                .'<p>Our site uses encrypted connections (HTTPS). Photos and documents are kept in private storage and shown only through links that expire. Gate and lockbox codes are encrypted in our database. Access to our back office is limited to the people who run the business and requires multi-factor authentication. No system is perfectly secure, but we work to protect your information and limit who can see it.</p>'

                .'<h2>Children</h2>'
                .'<p>Our website and services are meant for adults. We do not knowingly collect personal information from children under 13. If you believe a child has given us information, contact us and we will delete it.</p>'

                .'<h2>Your choices and rights</h2>'
                .'<ul>'
                .'<li><strong>Access and correction:</strong> you can see and update your contact details in the client portal, or email us to ask for a copy of the information we hold about you or to correct it.</li>'
                .'<li><strong>Deletion:</strong> you can email us to ask us to delete your information. We will delete what we can, but we may need to keep some records, such as invoices and payments, for legal, tax, or accounting reasons.</li>'
                .'<li><strong>Emails:</strong> you can unsubscribe from review requests and reminders at any time. Service emails about active work will still come through.</li>'
                .'</ul>'
                .'<p>To make a request, email hello@shorelinedoors.com. We may need to confirm your identity before acting on it.</p>'

                .'<h2>Changes to this policy</h2>'
                .'<p>We may update this policy from time to time. When we do, we will change the date at the top of this page. If a change is significant, we will let clients know by email or in the client portal.</p>'

                .'<h2>Contact us</h2>'
                .'<p>Shoreline Doors, Morehead City, North Carolina. Email: hello@shorelinedoors.com</p>',
        ],

        'terms' => [
            'title' => 'Terms of Service',
            'intro' => 'The terms that apply when you use our website, client portal, and online estimates and payments.',
            'body' => '<!-- DRAFT: have this reviewed before relying on it. -->'
                .'<p><strong>Last updated:</strong> {{ last_updated }}</p>'
                .'<p>These terms apply to your use of the Shoreline Doors website, our client portal, and the online tools we provide for quotes, estimates, and payments. Shoreline Doors ("we," "us," or "our") is a garage door and home repair business in Morehead City, North Carolina. By using the website or the portal, you agree to these terms.</p>'

                .'<h2>Using our website</h2>'
                .'<ul>'
                .'<li>Use the website only for lawful purposes and to request or manage work with us.</li>'
                .'<li>Do not send spam, upload harmful files, try to get into accounts or areas that are not yours, or interfere with how the site works.</li>'
                .'<li>The information on our website, including our hurricane and maintenance guidance, is general information. It is not engineering advice and does not replace the requirements of your local building official.</li>'
                .'</ul>'
                .'<p>The text, images, and design of our website belong to us or are used with permission. Please do not copy them for commercial use without asking.</p>'

                .'<h2>Quote requests and estimates</h2>'
                .'<ul>'
                .'<li>A quote request you send through our website is a request for us to contact you. It is not an estimate, and it does not commit you or us to any work.</li>'
                .'<li>Our estimates are prepared in QuickBooks Online and sent to you by email or through the client portal. An estimate is based on the information and photos available to us and is subject to on-site verification.</li>'
                .'<li>If conditions on site differ from what we expected, such as different measurements, hidden damage, or parts that are no longer available, we will tell you and give you a revised estimate before doing the extra work.</li>'
                .'<li>An estimate is valid until the expiration date shown on it, if any.</li>'
                .'<li>Sales tax, where it applies, is calculated in QuickBooks and shown on your estimate and invoice.</li>'
                .'<li>Some jobs may require a deposit. If so, it will be shown on your estimate or a separate invoice.</li>'
                .'</ul>'

                .'<h2>Approving estimates online</h2>'
                .'<p>You can approve an estimate in the client portal by typing your name and confirming your approval. By doing so, you agree that your typed name is your electronic signature, that it has the same effect as signing a paper copy, and that you are authorizing the work and price on that estimate. We record the name you type, the date and time, your IP address and browser details, and the version of the estimate you approved. If an estimate changes after you open it, you will need to review and approve the new version.</p>'
                .'<p>Only approve an estimate if you own the property or are authorized to approve work on it.</p>'

                .'<h2>Scheduling and access</h2>'
                .'<ul>'
                .'<li>Once you approve an estimate, we will work with you to schedule the job. Dates can move because of weather, parts availability, or other jobs running long. We will keep you posted if they do.</li>'
                .'<li>If you need to reschedule, let us know as early as you can.</li>'
                .'<li>If you give us gate codes, lockbox codes, or other access details, you authorize us to enter the property to do the work. For rentals, you are responsible for letting guests or tenants know when we will be there.</li>'
                .'<li>Please make sure the work area is clear and that pets are secured.</li>'
                .'</ul>'

                .'<h2>Your client portal account</h2>'
                .'<ul>'
                .'<li>Portal accounts are set up by invitation. You sign in with a one-time code or link sent to your email.</li>'
                .'<li>You are responsible for keeping your email account secure and for activity in your portal account. Tell us right away if you think someone else has gotten in.</li>'
                .'<li>If your account includes other people, such as a property manager or a family member, they can see and act on the projects, estimates, and invoices on that account, depending on the access we give them.</li>'
                .'<li>We may suspend or close a portal account that is misused, and we may close accounts that are no longer active.</li>'
                .'</ul>'

                .'<h2>Payments</h2>'
                .'<p>We send invoices through QuickBooks Online. You can pay online through QuickBooks Payments, which is provided by Intuit Inc. When you pay online, Intuit processes the payment, and Intuit\'s terms and privacy statement apply to that processing. We do not receive or store your full card or bank account numbers. Payment is due as shown on your invoice. You can also pay by any other method listed on the invoice.</p>'

                .'<h2>Photos and files you upload</h2>'
                .'<ul>'
                .'<li>You keep ownership of the photos and files you upload.</li>'
                .'<li>By uploading them, you give us permission to store, view, and use them to provide our services to you, including sharing them with suppliers or manufacturers when we need to identify parts or check a warranty.</li>'
                .'<li>We will not show your photos publicly, on our website or anywhere else, without your permission.</li>'
                .'<li>Only upload photos and files you have the right to share, and do not upload other people\'s private information.</li>'
                .'</ul>'
                .'<p>We may take photos of our work at your property to document the job. We will not post them publicly without your permission, and if we do, we will identify the town, not the street address.</p>'

                .'<h2>Reviews</h2>'
                .'<p>We may ask you to review our work. Reviews should reflect your own honest experience. If you give us a review or testimonial directly, you allow us to show it on our website with your name as you provided it and your town. You can ask us to remove it at any time.</p>'

                .'<h2>Other services</h2>'
                .'<p>Our website relies on services from other companies, such as Laravel Cloud for hosting, Intuit for estimates, invoices, and payments, and Cloudflare for file storage, email delivery, and form protection. Their own terms apply to your use of their services, and we are not responsible for them.</p>'

                .'<h2>Disclaimer and limitation of liability</h2>'
                .'<p>Our website and client portal are provided "as is" and "as available." We work to keep them accurate and running, but we do not promise they will be error-free or always available.</p>'
                .'<p>To the fullest extent allowed by law, Shoreline Doors is not liable for indirect, incidental, special, or consequential damages, or for lost profits or data, arising from your use of the website or the client portal. Our total liability for any claim related to the website or portal is limited to the amount you paid us for the specific work the claim relates to. The work we do at your property is covered by the estimate and invoice for that job. Nothing in these terms limits any liability that cannot be limited under North Carolina law.</p>'

                .'<h2>Governing law</h2>'
                .'<p>These terms are governed by the laws of the State of North Carolina, without regard to its conflict-of-law rules. Any dispute about these terms or the website will be handled in the state courts located in Carteret County, North Carolina, unless the law requires otherwise.</p>'

                .'<h2>Changes to these terms</h2>'
                .'<p>We may update these terms from time to time. When we do, we will change the date at the top of this page. Changes apply from that date forward and do not change estimates you have already approved.</p>'

                .'<h2>Contact us</h2>'
                .'<p>Shoreline Doors, Morehead City, North Carolina. Email: hello@shorelinedoors.com</p>',
        ],

    ],

];
