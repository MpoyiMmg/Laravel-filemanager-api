@component('mail::message')
<center>
    <img src="{{ asset('logo.svg') }}" style="width:40%" alt="dinflow">
</center><hr>

Un document est en attente de votre action

<b>Document :</b> contrat_din_technology.pdf <br>
<b>Emplacement :</b>  MANAGEMENT > JURIDIQUE > CONTRATS

@component('mail::button', ['url' => ''])
Ouvrir le document
@endcomponent

Bien Cordialement,<br>
DINFLOW
@endcomponent
