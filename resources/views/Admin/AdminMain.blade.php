<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Administration') }}
        </h2>
    </x-slot>


    <div class="py-12">
        <div class="col-md-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-4 px-4 mt-8 sm:grid-cols-4 sm:px-8">
                <div class="flex border-yellow-600 items-center bg-yellow-600 border rounded-lg overflow-hidden shadow">
                    <div class="px-4 bg-yellow-600">
                        <svg class="h-12 w-12 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.333 6.764a3 3 0 1 1 3.141-5.023M2.5 16H1v-2a4 4 0 0 1 4-4m7.379-8.121a3 3 0 1 1 2.976 5M15 10a4 4 0 0 1 4 4v2h-1.761M13 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-4 6h2a4 4 0 0 1 4 4v2H5v-2a4 4 0 0 1 4-4Z"/>
                        </svg>
                    </div>
                    <div class="px-4 text-white">
                        <h3 class="text-sm tracking-wider">Total Users</h3>
                        <p class="text-3xl">{{$userCount}}</p>
                    </div>
                </div>
                <div class="flex items-center bg-blue-400 border-blue-400 rounded-lg overflow-hidden shadow">
                    <div class="p-4 bg-blue-400"><svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-white" fill="none"
                                                      viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2">
                            </path>
                        </svg></div>
                    <div class="px-4 text-white">
                        <h3 class="text-sm tracking-wider">Total Assets</h3>
                        <p class="text-3xl">0</p>
                    </div>
                </div>
                <div class="flex items-center bg-indigo-400 border-indigo-400 rounded-lg overflow-hidden shadow">
                    <div class="p-4 bg-indigo-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-white" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10 14v4m-4 1h8M1 10h18M2 1h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1Z"/>
                            </path>
                        </svg></div>
                    <div class="px-4 text-white">
                        <h3 class="text-sm tracking-wider">Hardware Items</h3>
                        <p class="text-3xl">0</p>
                    </div>
                </div>
                <div class="flex items-center bg-red-400 border-red-400 rounded-lg overflow-hidden shadow">
                    <div class="p-4 bg-red-400"><svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-white" fill="none"
                                                     viewBox="0 0 24 24" stroke="currentColor">
                            <path d="M20,17H4V5h8V3H4C2.9,3,2,3.9,2,5v12c0,1.1,0.89,2,2,2h4v1c0,0.55,0.45,1,1,1h6c0.55,0,1-0.45,1-1v-1h4c1.1,0,2-0.9,2-2 v-3h-2V17z"/>
                            <path d="M17.71,13.29l3.59-3.59c0.39-0.39,0.39-1.02,0-1.41l0,0c-0.39-0.39-1.02-0.39-1.41,0L18,10.17V4c0-0.55-0.45-1-1-1h0 c-0.55,0-1,0.45-1,1v6.17l-1.89-1.88c-0.39-0.39-1.02-0.39-1.41,0l0,0c-0.39,0.39-0.39,1.02,0,1.41l3.59,3.59 C16.69,13.68,17.32,13.68,17.71,13.29z"/>
                        </svg></div>
                    <div class="px-4 text-white">
                        <h3 class="text-sm tracking-wider">Software Items</h3>
                        <p class="text-3xl">0</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="pt-6 px-4">
            <div class="w-full grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3 gap-4">
                <div class="bg-gray-700 shadow rounded-lg p-4 sm:p-6 xl:p-8  2xl:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-shrink-0">
                            <h3 class="text-base font-normal text-white">Something important here.</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
