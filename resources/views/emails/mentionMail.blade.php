@component('mail::message')
# NOTIFICATION

Vous avez été mentioné dans un commentaire

@component('mail::button', ['url' => 'https://dinflow.com/notifications/show/'.$id])
Voir le commentaire
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
