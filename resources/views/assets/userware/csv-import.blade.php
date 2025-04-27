<div class="p-2">
    <form wire:submit="save">
        <div
            x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploading = true"
            x-on:livewire-upload-finish="uploading = false; $wire.moveCsv();"
            x-on:livewire-upload-cancel="uploading = false"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
        >
        <input id="csv" name="csv" style="display: none" type="file" wire:model="csv" accept="text/csv" onchange="document.getElementById('go').click()" />
        <button onclick="document.getElementById('csv').click();" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-2.5 text-center inline-flex items-center me-2 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
            <x-gmdi-upload-file/> Import Users
        </button>
        @error('csv')  <span class="error text-white">{{ $message }}</span> @enderror
        <button style="display: none" type="submit">Go</button>
        <div wire:loading wire:target="csv" class="text-white pt-2">Uploading...</div>
        <div x-show="uploading">
            <progress max="100" x-bind:value="progress"></progress>
        </div>
        </div>
        <div
            x-data="{ show: false }"
            x-show="show"
            @toast-message.window="show = true; setTimeout(() => show = false, 3000)"
            id="toast-bottom-right"
            class="fixed flex items-center w-full max-w-xs p-4 space-x-4 text-gray-500 bg-white divide-x rtl:divide-x-reverse divide-gray-200 rounded-lg shadow right-5 bottom-5 dark:text-gray-400 dark:divide-gray-700 space-x dark:bg-gray-800"
            role="alert"
        >
            <x-gmdi-download-done />
            <div class="text-sm font-normal">
                File has been uploaded and will be processed shortly.
            </div>
        </div>

    </form>
</div>



<script>
    document.addEventListener('livewire:load', function () {
        @this.on('toast-message', () => {
            console.log("File Uploaded");
            window.dispatchEvent(new Event('toast-message'));
        });
    });
</script>

