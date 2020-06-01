<?php

/**
 * General Admin Settings Page
 */
$_['heading_title'] = 'EMS Online: Apple Pay';
$_['text_emspay_applepay'] = '<img src="view/image/payment/emspay_applepay.png" alt="EMS Online" title="EMS Online" />';
$_['text_extension'] = 'Extensions';

/**
 * Entry points
 */
$_['entry_status'] = 'Status:';
$_['entry_order_completed'] = 'Bestellung abgeschlossen:';
$_['entry_order_new'] = 'Neu Bestellung:';
$_['entry_order_expired'] = 'Bestellung abgelaufen:';
$_['entry_order_cancelled'] = 'Bestellung annulliert:';
$_['entry_order_processing'] = 'Bestellung wird bearbeitet:';
$_['entry_order_error'] = 'Bestellfehler:';
$_['entry_sort_order'] = 'Sortierreihenfolge:';
$_['entry_ems_api_key'] = 'EMS Online API Schüssel:';
$_['entry_ems_total'] = 'Gesamt:';
$_['entry_cacert'] = 'Bundle cURL ca.cert:';
$_['entry_send_webhook'] = 'Webhook-URL automatisch generieren:';
$_['entry_order_captured'] = 'Bestellung gefangen:';

/**
 * Text strings
 */
$_['text_button_save'] = 'Speichern';
$_['text_button_cancel'] = 'Abbrechen';
$_['text_enabled'] = 'Aktiviert';
$_['text_disabled'] = 'Deaktiviert';
$_['text_payments'] = 'Zahlungen';
$_['text_issuer_id'] = 'SWIFT/BIC';
$_['text_settings_saved'] = 'EMS Online: Apple Pay Einstellungen aktualisiert!';
$_['text_edit_ems'] = 'Bearbeiten EMS Online: Apple Pay Einstellungen';
$_['text_yes'] = 'Ja';
$_['text_no'] = 'Nein';

/**
 * Error messages
 */
$_['error_missing_api_key'] = 'EMS Online API schlüssel ist erforderlich!';

/**
 * Information text
 */
$_['info_help_api_key'] = 'Duplizieren Sie Ihre EMS Online API Schlüssel von Merchant Portal.';
$_['info_help_total'] = 'Die Zahlungsmethode wird gezeigt wenn die kasse insgesamt der Mindestbetrag erreicht.';
$_['info_plugin_not_configured'] = 'EMS Online: Apple Pay plugin ist nicht konfiguriert.';
$_['info_help_klarna_ip_filter'] = 'Wenn sie eingegeben werden, wird nur für diese IPs die Zahlungsmethode angezeigt. (Beispiel: 128.0.0.1, 255.255.255.255)';
$_['entry_klarna_ip_filter'] = 'IP-Filterung:';
$_['entry_klarna_test_api_key'] = 'Test API schlüssel:';
$_['info_help_klarna_test_api_key'] = 'Wenn eingegeben, wird dieser API-Schlüssel nur zu Testzwecken verwendet werden.';