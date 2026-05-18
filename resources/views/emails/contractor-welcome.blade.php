<x-mail::message>
@component('mail::message')
# Welcome, {{ $contractor['name'] }}

Your contractor account has been created.

**Login Email:** {{ $contractor['email'] }}
**Temporary Password:** {{ $contractor['initial_password'] }}

Please log in and change your password as soon as possible.

@component('mail::button', ['url' => "https://hub.omnirgb.com/login"])
Login
@endcomponent

Thanks,<br>
OMNI Team
@endcomponent
</x-mail::message>
