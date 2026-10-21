<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-bs-danger border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-bs-danger-hover active:bg-bs-danger-hover focus:outline-none focus:ring-2 focus:ring-bs-danger focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
