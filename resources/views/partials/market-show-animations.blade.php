@once
<style>
    @keyframes cart-pop { 0% { transform: scale(1); } 40% { transform: scale(1.18); } 100% { transform: scale(1); } }
    .cart-pop { animation: cart-pop .4s ease; }
    @keyframes picker-shake { 10%, 90% { transform: translateX(-1px); } 20%, 80% { transform: translateX(2px); } 30%, 50%, 70% { transform: translateX(-4px); } 40%, 60% { transform: translateX(4px); } }
    .picker-shake { animation: picker-shake .5s cubic-bezier(.36,.07,.19,.97); }
    @media (prefers-reduced-motion: reduce) { .cart-pop, .picker-shake { animation: none; } }
</style>
@endonce
