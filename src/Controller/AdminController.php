<?php

declare(strict_types=1);

namespace Yawasla\Controller;

// TODO auth (à retirer une fois fait) : ce constructeur ne protège encore rien, aucune route dans Admin/ d'ici là
// - vérifier la session ici, pour toute la zone ; sinon 404 ou redirection vers la connexion
// - connexion en deux temps : identifiants, puis lien à usage unique de 5 min par mail, validé par un bouton
// - réponse d'échec uniforme, limitation par IP silencieuse
// - session PHP native : cookie Secure, HttpOnly, SameSite=Lax, ID régénéré à la connexion
// - CSRF sur tous les formulaires de l'admin
abstract class AdminController extends Controller
{
    public function __construct()
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
}
