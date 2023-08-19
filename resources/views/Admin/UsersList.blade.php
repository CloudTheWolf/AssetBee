<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Administration') }} > {{__('Manage Users')}}
        </h2>
    </x-slot>

    <div class="col-md-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="pt-6 px-4">
            <div class="w-full grid grid-cols-1 xl:grid-cols-1 2xl:grid-cols-1 gap-4">
                <div class="bg-gray-700 shadow rounded-lg p-4 sm:p-6 xl:p-8  2xl:col-span-2">
                    <div class="grid items-center justify-between mb-4 grid-cols-1">
                        <div style="display: block; margin-left: auto; margin-right: 0">
                        <x-primary-button data-modal-target="addUser" data-modal-toggle="addUser">
                            <x-gmdi-person-add class="w-5 h-5 text-white dark:text-white"/>&nbsp;Add User
                        </x-primary-button>
                        </div>
                        <div class="flex-shrink-0">
                            <h4 class="text-base font-normal text-white">Manage Users</h4>
                            <hr>
                            <!-- Main modal -->
                            <div id="addUser" tabindex="-1" aria-hidden="true" class="fixed top-0 left-0 right-0 z-50 hidden w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
                                <div class="relative w-full max-w-2xl max-h-full">
                                    <!-- Modal content -->
                                    <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                                        <!-- Modal header -->
                                        <div class="flex items-start justify-between p-4 border-b rounded-t dark:border-gray-600">
                                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                                Add New User
                                            </h3>
                                            <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-hide="addUser">
                                                <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                                                </svg>
                                                <span class="sr-only">Close modal</span>
                                            </button>
                                        </div>
                                        <!-- Modal body -->
                                        <form action="" method="post">
                                            @method('patch')
                                            @csrf
                                            <div class="p-6 space-y-6">
                                                <div>
                                                    <x-input-label for="name" :value="__('Name')" />
                                                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" placeholder="Barry B. Benson" :value="old('name', '')" required autofocus autocomplete="name" />
                                                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                                                </div>
                                                <div>
                                                    <x-input-label for="email" :value="__('Email')" />
                                                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" placeholder="user@asset.bee" :value="old('email', '')" required autocomplete="email" />
                                                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                                                </div>

                                                <div>
                                                    <x-input-label for="password" :value="__('Password')" />
                                                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                                                    <x-input-error :messages="$errors->setPassword->get('password')" class="mt-2" />
                                                </div>

                                                <div>
                                                    <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                                                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                                                    <x-input-error :messages="$errors->setPassword->get('password_confirmation')" class="mt-2" />
                                                </div>
                                                <div>
                                                    <label for="countries" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Group</label>
                                                    <select id="countries" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                                        <option value="3">Administrator</option>
                                                        <option value="2">Super User</option>
                                                        <option selected value="1">User</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <!-- Modal footer -->
                                            <div class="flex items-center p-6 space-x-2 border-t border-gray-200 rounded-b dark:border-gray-600">
                                                <button type="submit" class="text-white bg-yellow-500 hover:bg-yellow-700 focus:ring-4 focus:outline-none focus:ring-yellow-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-yellow-500 dark:hover:bg-yellow-800 dark:focus:ring-yellow-800">Add User</button>
                                                <button data-modal-hide="addUser" type="button" class="text-orange-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-orange-300 rounded-lg border border-orange-200 text-sm font-medium px-5 py-2.5 hover:text-orange-900 focus:z-10 dark:bg-gray-700 dark:text-orange-300 dark:border-orange-600-500 dark:hover:text-white dark:hover:bg-orange-600 dark:focus:ring-orange-600">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <table class="min-w-[100%] text-left text-sm font-light">
                                <thead class="border-b font-medium dark:border-yellow-100">
                                <tr>
                                    <th scope="col" class="p-6 text-center text-yellow-500">Name</th>
                                    <th scope="col" class="p-6 text-center text-yellow-500">Email</th>
                                    <th scope="col" class="p-6 text-center text-yellow-500">Role</th>
                                    <th scope="col" class="p-3 text-center text-yellow-500" style="width: 110px !important;"></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($users as $user)
                                <tr class="border-b transition duration-300 ease-in-out hover:bg-gray-600 dark:border-yellow-200 dark:hover:bg-gray-600">
                                    <td class="whitespace-nowrap text-center px-6 py-4 font-medium text-white">{{$user->name}}</td>
                                    <td class="whitespace-nowrap text-center px-6 py-4 font-medium text-white">{{$user->email}}</td>
                                    <td class="whitespace-nowrap text-center px-6 py-4 font-medium text-white">{{$user->role}}</td>
                                    <td onclick="window.location = '{{route('admin-user',['id' => $user->id])}}';" class="whitespace-nowrap text-center px-6 py-4 font-medium text-yellow-400 hover:text-yellow-300 hover:font-medium cursor-pointer">
                                        <svg class="w-5 h-5 text-yellow-700 dark:text-yellow-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 18">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.109 17H1v-2a4 4 0 0 1 4-4h.87M10 4.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Zm7.95 2.55a2 2 0 0 1 0 2.829l-6.364 6.364-3.536.707.707-3.536 6.364-6.364a2 2 0 0 1 2.829 0Z"/>
                                        </svg>
                                    </td>
                                </tr>
                                @endforeach
                                </tbody>
                            </table>

                        </div>
                        <br>
                        <div style="display: flex; justify-content: center; align-items: center;">
                            {{ $users->onEachSide(1)->links() }}
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
