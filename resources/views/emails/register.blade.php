@component('mail::message')
<center>
    <img src="{{ asset('logo.svg') }}" style="width:40%" alt="dinflow">
</center><hr>

Votre compte vient d'être créé sur dinflow, 
Veuillez suivre ce lien pour vous connecter.

@component('mail::button', ['url' => 'www.dinflow.com'])
Login
@endcomponent

Bien Cordialement,<br>
DINFLOW
@endcomponent
