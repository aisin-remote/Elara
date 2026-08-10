@if ($errors->any())
    <x-alert variant="error" title="Please fix the following:" :dismissible="false" tabindex="-1">
        <ul class="list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
