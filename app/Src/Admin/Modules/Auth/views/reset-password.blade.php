<div class="d-flex flex-column flex-lg-row flex-column-fluid">
    <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
        <div class="d-flex flex-center flex-column flex-lg-row-fluid">
            <div class="w-lg-500px p-10">
                <form class="form w-100" wire:submit="submit" method="post">
                @csrf
                    <x-panel::loader target="submit" />
                    <div class="text-center mb-11">
                        <h1 class="text-gray-900 fw-bolder mb-3">{{__('admin.reset-password.title')}}</h1>
                        <div
                            class="text-gray-500 fw-semibold fs-6">{{__('admin.reset-password.sub_title',['site_name' => config('app.name')])}}</div>
                    </div>
                    <x-panel::alert />
                    <div class="fv-row mb-4">
                        <input type="text" placeholder="Email" name="email" autocomplete="off"
                               wire:model.blur="email"
                               readonly
                               class="form-control bg-gray-200" />
                        <x-panel::error name="email" />
                    </div>
                    <div class="fv-row mb-4">
                        <input type="text" placeholder="Password" name="password" autocomplete="off"
                               wire:model.blur="password"
                               class="form-control bg-transparent" />
                        <x-panel::error name="password" />
                    </div>
                    <div class="fv-row mb-4">
                        <input type="text" placeholder="confirm password" name="password_confirmation" autocomplete="off"
                               wire:model.blur="password_confirmation"
                               class="form-control bg-transparent" />
                        <x-panel::error name="password_confirmation" />
                    </div>
                    <div class="d-flex flex-wrap justify-content-center pb-lg-0">
                        <button type="submit" id="kt_password_reset_submit" class="btn d-flex align-items-center text-white fw-semibold me-4"
                                style="background: #1e3a8a; border: none; box-shadow: none !important;">
                            <span class="indicator-label">{{__('app.panel.submit')}}</span>
                        </button>
                        <a href="{{route('admin.auth.login')}}" wire:navigate
                           class="btn btn-light">{{__('app.panel.cancel')}}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
