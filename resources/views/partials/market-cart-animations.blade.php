@once
<style>
    /* Add-to-cart micro-interactions — shared by mobile & desktop market pages */
    @keyframes cart-bump { 0%,100% { transform: none } 25% { transform: scale(1.3) rotate(-10deg) } 55% { transform: scale(.92) rotate(7deg) } 80% { transform: scale(1.06) } }
    .cart-bump { animation: cart-bump .6s cubic-bezier(.2,.8,.3,1.2); transform-origin: center; }
    @keyframes cart-pop { 0%,100% { transform: none } 40% { transform: scale(1.5) } }
    .cart-pop { animation: cart-pop .5s ease; }
    @keyframes cart-plus { 0% { opacity: 0; transform: translateY(2px) scale(.6) } 25% { opacity: 1 } 100% { opacity: 0; transform: translateY(-28px) scale(1.15) } }
    .cart-plus { position: absolute; top: -4px; right: 2px; font-size: 12px; font-weight: 900; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.3); pointer-events: none; animation: cart-plus .75s ease forwards; }
    @media (prefers-reduced-motion: reduce) { .cart-bump,.cart-pop,.cart-plus { animation: none !important } }
</style>
@endonce
