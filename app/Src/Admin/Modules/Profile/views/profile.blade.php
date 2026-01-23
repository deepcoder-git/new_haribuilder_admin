<div>
    <x-panel::alert />
    <div class="card mb-5 mb-xl-10">
        <x-panel::loader target="save" />
        <div class="card-header border-0">
            <div class="card-title m-0">
                <h3 class="fw-bold m-0">Update Profile</h3>
            </div>
        </div>
        <form class="form" method="post" wire:submit="save"
              enctype="multipart/form-data">
            @csrf
            <div class="card-body border-top p-9">
                <div class="row mb-6">
                    <label class="col-lg-3 col-form-label fw-semibold fs-6 required"
                           for="thumb_image">{{__('admin.input.profile_image')}}</label>
                    <div class="col-lg-9 fv-row">
                        <input type="file"
                               id="thumb_image" class="form-control form-control-lg"
                               wire:model="thumbImage"
                               accept="image/*" />
                        <x-panel::error name="thumb_image" />
                    </div>
                </div>
                <div class="row mb-6">
                    <label class="col-lg-3 col-form-label fw-semibold fs-6 required"
                           for="name">{{__('admin.input.name')}}</label>
                    <div class="col-lg-9 fv-row">
                        <input type="text"
                               name="name" id="name" class="form-control form-control-lg form-control-solid"
                               wire:model.blur="name"
                               placeholder="Please enter  name">
                        <x-panel::error name="name" />
                    </div>
                </div>
                <div class="row mb-6">
                    <label class="col-lg-3 col-form-label fw-semibold fs-6 required"
                           for="email">{{__('admin.input.email')}}</label>
                    <div class="col-lg-9 fv-row">
                        <input type="text"
                               name="email" id="email" class="form-control form-control-lg form-control-solid"
                               wire:model.blur="email"
                               placeholder="Please enter email">
                        <x-panel::error name="email" />
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end py-6 px-9">
                <a href="{{route('admin.dashboard')}}"
                   class="btn btn-light btn-active-light-primary me-2">{{__('app.panel.cancel')}}</a>
                <button type="submit" class="btn d-flex align-items-center text-white fw-semibold"
                        style="background: #1e3a8a; border: none; box-shadow: none !important;">
                    {{__('app.panel.submit')}}
                </button>
            </div>
        </form>
    </div>
</div>
