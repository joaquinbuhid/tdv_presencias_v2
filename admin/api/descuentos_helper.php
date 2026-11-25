<?php
/** Token CSRF del módulo Descuentos (compartido entre la página y la API). */
function descCsrf(): string
{
    if (empty($_SESSION['descuentos_csrf'])) $_SESSION['descuentos_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['descuentos_csrf'];
}
