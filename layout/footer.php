<?php
// layout/footer.php
?>
<!-- Inclusion de Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<footer class="text-center text-muted py-3 small">
    CS ELMA SOMBE &copy; <?= date('Y') ?>
</footer>

<!-- ICÔNE FLOTTANTE EN POSITION FIXE (NOTIFICATIONS) -->
<?php if (isset($_SESSION['user']['id'])): ?>
<div id="notif-floating-container" style="position: fixed; bottom: 25px; right: 25px; z-index: 9999;">
    <!-- Bouton Cloche avec Icône Bootstrap -->
    <button id="notifBtn" type="button"
        class="btn btn-primary rounded-circle shadow-lg position-relative d-flex align-items-center justify-content-center"
        style="width: 58px; height: 58px;">
        <i class="bi bi-bell-fill fs-4"></i>
        <span id="notifBadge"
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light"
            style="display: none; font-size: 11px;">
            0
        </span>
    </button>

    <!-- Panneau de notification -->
    <div id="notifBox" class="card border-0 shadow-lg rounded-4 position-absolute"
        style="display: none; bottom: 70px; right: 0; width: 330px; z-index: 10000;">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                <h6 class="fw-bold mb-0 text-dark">Actions en attente</h6>
            </div>
            <!-- Bouton Fermer (X) -->
            <button id="closeNotifBtn" type="button" class="btn-close" aria-label="Fermer"></button>
        </div>
        <div class="card-body p-2" id="notifContent">
            <div class="text-center py-3 text-muted small">Vérification...</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('notifBtn');
    const box = document.getElementById('notifBox');
    const closeBtn = document.getElementById('closeNotifBtn');
    const badge = document.getElementById('notifBadge');
    const content = document.getElementById('notifContent');

    // Conserve le total précédent pour détecter l'arrivée d'une nouvelle notification
    let previousTotal = null;

    function checkPendingActions() {
        const notifUrl = window.location.origin + '/directeur/layout/notification.php';

        fetch(notifUrl)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const currentTotal = data.total;

                    // Mise à jour du badge
                    if (currentTotal > 0) {
                        badge.textContent = currentTotal;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }

                    // 1. Contenu vide
                    if (!data.items || data.items.length === 0) {
                        content.innerHTML = `
                            <div class="text-center py-3 text-muted small">
                                <i class="bi bi-check-circle-fill text-success fs-5 d-block mb-1"></i>
                                Tout est à jour ! Aucune action en attente.
                            </div>`;
                        previousTotal = 0;
                        return;
                    }

                    // 2. Génération de la liste des items
                    let html = '<div class="list-group list-group-flush rounded-3">';
                    data.items.forEach(item => {
                        html += `
                            <a href="${item.lien}" class="list-group-item list-group-item-action p-3 border-0 border-bottom">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-primary small">${item.titre}</span>
                                    <span class="badge bg-warning text-dark rounded-pill">${item.badge}</span>
                                </div>
                                <div class="small text-secondary mb-0">${item.message}</div>
                            </a>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;

                    // 3. OUVERTURE EN TEMPS RÉEL
                    // S'ouvre UNIQUEMENT si une nouvelle notification arrive (le total augmente)
                    if (previousTotal !== null && currentTotal > previousTotal) {
                        box.style.display = 'block';
                    }

                    // Mise à jour du total enregistré
                    previousTotal = currentTotal;
                }
            })
            .catch(err => {
                console.error("Erreur chargement notifications:", err);
            });
    }

    if (btn) {
        // Toggle manuel du panneau au clic sur la cloche
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' :
                'none';
        });

        // Fermer le panneau au clic sur le bouton (X)
        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                box.style.display = 'none';
            });
        }

        // Fermer le panneau en cliquant à l'extérieur
        document.addEventListener('click', function(e) {
            if (!box.contains(e.target) && !btn.contains(e.target)) {
                box.style.display = 'none';
            }
        });

        // Premier contrôle discret au chargement de la page
        checkPendingActions();

        // Vérification en arrière-plan toutes les 5 secondes
        setInterval(checkPendingActions, 5000);
    }
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>