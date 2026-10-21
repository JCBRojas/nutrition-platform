<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-bs-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-bs-primary-hover focus:bg-bs-primary-hover active:bg-bs-primary-hover focus:outline-none focus:ring-2 focus:ring-bs-primary focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
