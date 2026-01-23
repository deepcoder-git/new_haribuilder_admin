@props(['form','name','prevImage'=>null])
<div
    x-data="imageCapture"
    x-on:livewire-upload-start="uploading = true"
    x-on:livewire-upload-finish="function (){
    this.uploading = false;
    const wireValue = this.$wire.form?.['{{$name}}'] || '';
    this.uploadedFilename = wireValue.replace('livewire-file:', '');
}"
    x-on:livewire-upload-cancel="uploading = false"
    x-on:livewire-upload-error="uploading = false"
    x-on:livewire-upload-progress="progress = $event.detail.progress">
    <div
        style="background-color: #F8FBFF;border: 1px solid white;box-shadow: var(--bs-box-shadow);">
        <div class="d-flex justify-content-center align-items-center"
             style="cursor: pointer;height: 200px">
            <label for="{{$name}}">
                <video style="height: 200px;width:100%" autoplay x-ref="videoEl" x-cloak x-show="isCameraOn"></video>
                @if($form->getPropertyValue($name)||$prevImage)
                    <img class="w-100" style="height: 200px" x-show="!isCameraOn" x-cloak
                         src="{{$form->{$name}?->temporaryUrl()??$prevImage}}">
                @endif
                @if(!$form->getPropertyValue($name)&&!$prevImage)
                    <i class="fa-solid fa-upload fa-4x" style="font-size: 40px" x-show="!isCameraOn&&!uploadedFilename"
                       x-cloak></i>
                @endif
                <input type="file" class="d-none"
                       x-ref="fileInput"
                       x-on:change="fileSelected"
                       wire:model="form.{{$name}}" accept="image/*">
            </label>
        </div>
    </div>
    <div x-show="uploading">
        <div class="progress">
            <div class="progress-bar progress-bar-striped bg-success"
                 :style="`width:${progress}%`"
                 x-text="progress+'%'"></div>
        </div>
        <button class="btn btn-danger btn-sm"
                wire:click="$cancelUpload('form.{{$name}}')">
            Cancel Upload
        </button>
    </div>
    <div class="btn-group btn-group-sm w-100" x-show="!uploading" x-cloak>
        <button x-show="!isCameraOn" x-cloak type="button" class="btn  btn-primary"
                x-on:click="captureCamera">
            <i class="fa-solid fa-camera"></i>
            Camera
        </button>
        <button x-show="!isCameraOn" x-cloak type="button" class="btn btn-info"
                x-on:click="triggerUpload">
            <i class="fa-solid fa-upload"></i>
            Upload
        </button>
        <button x-show="isCameraOn" x-cloak type="button" class="btn btn-warning"
                x-on:click="capture">
            <i class="fa-solid fa-camera"></i>
            Capture
        </button>
        <button type="button" class="btn btn-danger" x-cloak x-show="isCameraOn"
                x-on:click="stopWebCamp">
            <i class="fa-solid fa-xmark"></i>
            Cancel
        </button>
        <button type="button" x-show="uploadedFilename&&!isCameraOn" x-cloak class="btn btn-danger"
                x-on:click="removeUpload">
            <i class="fa-solid fa-trash"></i>
            Cancel
        </button>
    </div>
</div>
@script
<script>
    Alpine.data("imageCapture", function() {
        return {
            width: 0,
            height: 0,
            uploading: false,
            progress: 0,
            uploadedFilename: null,
            isCameraOn: false,
            triggerUpload: function() {
                this.$refs.fileInput.click();
            },
            stopWebCamp: function() {
                this.isCameraOn = false;
                this.$refs.videoEl?.srcObject?.getTracks()?.forEach((track) => track.stop());
            },
            fileSelected: function() {
                this.stopWebCamp();
                this.removeUpload();
            },
            captureCamera: function() {
                const th = this;
                const el = th.$refs.videoEl;
                navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: "environment"
                    }
                })
                    .then((stream) => {
                        th.isCameraOn = true;
                        el.srcObject = stream;
                        el.onloadedmetadata = () => {
                            th.width = el.videoWidth;
                            th.height = el.videoHeight;
                        };
                    })
                    .catch((err) => alert(err.message));
            },
            capture: function() {
                const th = this;
                const canvas = document.createElement("canvas");
                canvas.width = this.width;
                canvas.height = this.height;
                const context = canvas.getContext("2d");
                context.drawImage(this.$refs.videoEl, 0, 0, canvas.width, canvas.height);
                context.canvas.toBlob(function(blob) {
                    $wire.upload(th.modelName, blob, function(uploadedFilename) {
                        th.fileSelected();
                        th.uploadedFilename = uploadedFilename;
                        th.$wire.$refresh();
                    }, () => {
                        alert("Something Went Wrong");
                    });
                });
            },
            removeUpload: function() {
                if (this.uploadedFilename) {
                    $wire.removeUpload(this.modelName, this.uploadedFilename);
                    this.uploadedFilename = null;
                }
            }
        };
    });
</script>
@endscript
