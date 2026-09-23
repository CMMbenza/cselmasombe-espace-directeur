<script>
document.addEventListener("DOMContentLoaded", function () {

    // Vérifier si le navigateur supporte les notifications
    if (!("Notification" in window)) {
        console.log("Les notifications ne sont pas supportées par ce navigateur.");
        return;
    }

    // Vérifier l'état actuel de l'autorisation
    if (Notification.permission === "granted") {

        // Déjà autorisé : ne rien afficher
        console.log("Notifications déjà autorisées.");

    } else if (Notification.permission === "denied") {

        // Refusé : ne pas redemander
        console.log("Notifications refusées par l'utilisateur.");

    } else if (Notification.permission === "default") {

        // Aucune décision n'a encore été prise
        Notification.requestPermission().then(function (permission) {

            if (permission === "granted") {
                console.log("Notifications autorisées.");
            } else if (permission === "denied") {
                console.log("Notifications refusées.");
            }

        });

    }

});
</script>