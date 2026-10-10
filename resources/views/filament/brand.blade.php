<div class="viralyx-sidebar-brand" style="display:flex;align-items:center;justify-content:flex-start;gap:.6rem;min-height:2.5rem;width:100%;white-space:nowrap;overflow:hidden">
    <img
        src="{{ asset('images/viralyx-isotipo.svg') }}"
        alt="VIRALYX"
        style="display:block;width:2.1rem;height:2.1rem;min-width:2.1rem;object-fit:contain"
    >
    <span
        x-show="$store.sidebar.isOpen"
        x-transition.opacity.duration.150ms
        style="font-size:1.08rem;font-weight:800;letter-spacing:.1em;line-height:1;background:linear-gradient(100deg,#08baf8 0%,#6550f5 55%,#b33ff4 100%);-webkit-background-clip:text;background-clip:text;color:transparent"
    >VIRALYX</span>
</div>