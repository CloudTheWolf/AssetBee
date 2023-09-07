<div class="text-center">
    @if($hasUpdate)
    <div class="p-2 border border-yellow-500 hover:bg-yellow-500 hover:text-gray-900 hover:cursor-pointer items-center text-white lg:rounded-full flex lg:inline-flex" role="alert">
        <span class="flex rounded-full bg-yellow-600 text-white uppercase px-2 py-1 text-xs font-bold mr-3">{{$currentVersion}} => {{$newVersion}}</span>
        <span class="flex font-semibold mr-2 text-left flex-auto">A New Version Is Available</span>
        <svg class="fill-current opacity-75 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M12.95 10.707l.707-.707L8 4.343 6.586 5.757 10.828 10l-4.242 4.243L8 15.657l4.95-4.95z"/></svg>
    </div>
    @endif
</div>
