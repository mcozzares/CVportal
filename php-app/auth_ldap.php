<?php
function authenticate_user_ldap($username, $password) {
    // Obtenemos configuración LDAP desde variables de entorno
    $ldap_host = getenv('LDAP_HOST') ?: 'openldap';
    $ldap_port = getenv('LDAP_PORT') ?: 389;
    $ldap_dn_base = getenv('LDAP_BASE_DN') ?: 'dc=cvportal,dc=local';
    
    $ldap_admin_dn = getenv('LDAP_ADMIN_DN') ?: 'cn=admin,dc=cvportal,dc=local';
    $ldap_admin_pass = getenv('LDAP_ADMIN_PASS') ?: 'adminpass';

    // Establecer conexión con el servidor LDAP
    $ldap_conn = ldap_connect($ldap_host, $ldap_port);
    if (!$ldap_conn) {
        return ['success' => false, 'error' => "No se pudo conectar con el servidor LDAP."];
    }
    
    // Configuración del protocolo y referencias
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // Autenticación como administrador para buscar al usuario
    if (!@ldap_bind($ldap_conn, $ldap_admin_dn, $ldap_admin_pass)) {
        return ['success' => false, 'error' => "Error de configuración LDAP: No se puede conectar como admin."];
    }

    // Búsqueda del usuario por su identificador (UID)
    $filter = "(uid=$username)";
    $result = ldap_search($ldap_conn, $ldap_dn_base, $filter);
    $entries = ldap_get_entries($ldap_conn, $result);

    if ($entries['count'] == 0) {
        ldap_unbind($ldap_conn);
        return ['success' => false, 'error' => "Usuario no encontrado en LDAP."];
    }

    $user_dn = $entries[0]['dn'];
    $gidNumber = $entries[0]['gidnumber'][0] ?? 0;

    // Verificamos la contraseña del usuario encontrado
    if (!@ldap_bind($ldap_conn, $user_dn, $password)) {
        ldap_unbind($ldap_conn);
        return ['success' => false, 'error' => "Contraseña incorrecta."];
    }

    // Asignación de rol según el grupo (GID 5000 = Admin IT)
    $role = 'user';
    if ($gidNumber == 5000) {
        $role = 'admin';
    }

    ldap_unbind($ldap_conn);

    return [
        'success' => true,
        'user_id' => $username,
        'username' => $username,
        'role' => $role
    ];
}
