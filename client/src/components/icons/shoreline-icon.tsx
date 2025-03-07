export function ShorelineIcon({ className = "" }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 100 50"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M0 40 Q25 40 35 30 Q45 20 55 25 Q65 30 75 20 Q85 10 100 15"
        stroke="currentColor"
        strokeWidth="2"
        fill="none"
      />
      <path
        d="M0 45 Q20 45 40 35 Q60 25 80 30 Q90 32 100 25"
        stroke="currentColor"
        strokeWidth="2"
        fill="none"
        opacity="0.5"
      />
    </svg>
  );
}
