<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ══════════════════════════════════════════════════════════════
   RUOLI UTENTE GESTIONALE
   Gerarchia: amministratore > mssg_admin > mssg_capo > mssg_operaio > mssg_cliente
══════════════════════════════════════════════════════════════ */

function mssg_register_roles() {

    // Admin gestionale (titolare / ufficio)
    add_role( 'mssg_admin', 'Amministratore Gestionale', array(
        'read'              => true,
        'mssg_view_all'     => true,
        'mssg_edit_all'     => true,
        'mssg_delete_all'   => true,
        'mssg_manage'       => true,
    ));

    // Capo cantiere
    add_role( 'mssg_capo', 'Capo Cantiere', array(
        'read'              => true,
        'mssg_view_all'     => true,
        'mssg_edit_assigned'=> true,
        'mssg_report'       => true,
    ));

    // Operaio / Tecnico
    add_role( 'mssg_operaio', 'Operaio', array(
        'read'              => true,
        'mssg_view_assigned'=> true,
        'mssg_report'       => true,
    ));

    // Cliente finale (view-only sui propri cantieri)
    add_role( 'mssg_cliente', 'Cliente', array(
        'read'              => true,
        'mssg_view_own'     => true,
    ));
}

function mssg_remove_roles() {
    remove_role( 'mssg_admin' );
    remove_role( 'mssg_capo' );
    remove_role( 'mssg_operaio' );
    remove_role( 'mssg_cliente' );
}

/* ── Helpers ruoli ─────────────────────────────────────── */

/**
 * Restituisce il ruolo gestionale principale dell'utente.
 * Gli admin WP ereditano tutti i permessi.
 */
function mssg_get_primary_role( $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return '';

    $gestionale_roles = array( 'mssg_admin', 'mssg_capo', 'mssg_operaio', 'mssg_cliente' );

    if ( in_array( 'administrator', (array) $user->roles ) ) return 'administrator';

    foreach ( $gestionale_roles as $role ) {
        if ( in_array( $role, (array) $user->roles ) ) return $role;
    }
    return '';
}

/**
 * Controlla se l'utente ha un ruolo gestionale (o è admin WP).
 */
function mssg_is_gestionale_user( $user_id ) {
    return mssg_get_primary_role( $user_id ) !== '';
}

/**
 * Controlla se l'utente ha uno specifico ruolo.
 */
function mssg_user_has_role( $user_id, $role ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return false;
    return in_array( $role, (array) $user->roles );
}

/**
 * Etichetta leggibile del ruolo.
 */
function mssg_get_role_label( $user_id ) {
    $labels = array(
        'administrator' => 'Super Admin',
        'mssg_admin'    => 'Amministratore',
        'mssg_capo'     => 'Capo Cantiere',
        'mssg_operaio'  => 'Operaio',
        'mssg_cliente'  => 'Cliente',
    );
    $role = mssg_get_primary_role( $user_id );
    return $labels[ $role ] ?? '';
}
