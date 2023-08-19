<button {{ $attributes->merge(['type' => 'reset','class' => 'inline-flex items-center px-4 py-2 bg-transparent
hover:bg-red-500 border border-red-500 rounded-md font-semibold text-xs text-red-500 hover:text-white uppercase
tracking-widest focus:bg-red-500 active:bg-red-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
