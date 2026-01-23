<div class="d-flex flex-column flex-lg-row flex-column-fluid">
    <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
        <div class="d-flex flex-center flex-column flex-lg-row-fluid">
            <div class="w-lg-500px p-10">
                <form class="form w-100" wire:submit="register">
                    <div class="text-center mb-11">
                        <h1 class="text-gray-900 fw-bolder mb-3">Sign Up</h1>
                        <div class="text-gray-500 fw-semibold fs-6">Create your {{ config('app.name') }} Account</div>
                    </div>
                    <x-panel::loader target="register" />
                    <x-panel::alert />
                    
                    <div class="fv-row mb-8">
                        <input type="text" placeholder="Full Name" name="name" autocomplete="off"
                               wire:model.blur="name"
                               class="form-control bg-transparent" />
                        <x-panel::error name="name" />
                    </div>

                    <div class="fv-row mb-8">
                        <input type="email" placeholder="Email" name="email" autocomplete="off"
                               wire:model.blur="email"
                               class="form-control bg-transparent" />
                        <x-panel::error name="email" />
                    </div>

                    <div class="fv-row mb-8">
                        <select wire:model.blur="board" class="form-select bg-transparent">
                            <option value="">Select Board</option>
                            @foreach($this->getBoards() as $board)
                                <option value="{{ $board->value }}">{{ $board->name }}</option>
                            @endforeach
                        </select>
                        <x-panel::error name="board" />
                    </div>

                    <div class="fv-row mb-3">
                        <div class="input-group mt-3" x-data="{showPassword:false}">
                            <input x-bind:type="showPassword?'text':'password'" type="password" placeholder="Password" name="password" autocomplete="off"
                                   wire:model.blur="password"
                                   class="form-control bg-transparent">
                            <span class="input-group-text cursor-pointer" x-on:click="showPassword=!showPassword;">
                                <i class="fa" x-bind:class="showPassword?'fa-eye':'fa-eye-slash'"></i>
                            </span>
                        </div>
                        <x-panel::error name="password" />
                    </div>

                    <div class="fv-row mb-8">
                        <div class="input-group mt-3" x-data="{showPassword:false}">
                            <input x-bind:type="showPassword?'text':'password'" type="password" placeholder="Confirm Password" name="password_confirmation" autocomplete="off"
                                   wire:model.blur="password_confirmation"
                                   class="form-control bg-transparent">
                            <span class="input-group-text cursor-pointer" x-on:click="showPassword=!showPassword;">
                                <i class="fa" x-bind:class="showPassword?'fa-eye':'fa-eye-slash'"></i>
                            </span>
                        </div>
                        <x-panel::error name="password_confirmation" />
                    </div>

                    <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                        <div></div>
                        <a href="{{route('admin.auth.login')}}" wire:navigate class="link-primary">
                            Already have an account? Sign In
                        </a>
                    </div>
                    <div class="d-grid mb-10">
                        <button type="submit" id="kt_sign_up_submit" class="btn d-flex align-items-center justify-content-center text-white fw-semibold"
                                style="background: #1e3a8a; border: none; box-shadow: none !important;">
                            Sign Up
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

