{{-- resources/views/emails/verify-email.blade.php --}}
<x-mail::message>
    # Email Verification

    Thank you for registering! Please click the button below to verify your email address.

    <x-mail::button :url="$verificationUrl">
        Verify Email Address
    </x-mail::button>

    If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:
    [{{ $verificationUrl }}]({{ $verificationUrl }})

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>
