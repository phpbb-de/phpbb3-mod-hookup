<?php
/**
*
* hookup mod [Deutsch - Du]
*
* @package language
* @copyright (c) 2006-2008 Pyramide (Frank Dreyer), (c) 2008 gn#36 (Martin Beckmann)
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// Define categories and permission types
$lang = array_merge($lang, array(
	'ACTIVE_DATE'			=> 'Aktiver Termin',
	'ACTIVE_DATE_SET'		=> 'Das aktive Datum wurde auf %s gesetzt.',
	'ACTIVE_DATE_UNSET'		=> 'Das aktive Datum wurde zurückgesetzt.',
	'ADD_HOOKUP'			=> 'Terminplaner',
	'ADD_HOOKUP_DESC'		=> 'Diesem Thema einen Terminplaner hinzufügen',
	'ADD_HOOKUP_REACTIVATE'	=> 'Terminplaner reaktivieren',
	'ADD_HOOKUP_REACTIVATE_EXPLAIN'=> 'Es existieren noch Daten eines zuvor deaktivierten Terminplaners. Wenn du diesen reaktivierst, sind die alten Benutzer und Termine wieder verfügbar.',
	//'ADDED_AT_BY'			=> 'hinzugefügt am %1s von %2s',

	'CANNOT_ADD_PAST'		=> 'Kann kein Datum in der Vergangenheit hinzufügen',
	'CLEAR'					=> 'Löschen',
	'CLEAR_TITLE'			=> 'Löscht die ausgewählten Daten',
	'COMMENT'				=> 'Kommentar',

	'DATE_ALREADY_ADDED'			=> 'Das Datum %1s wurde diesem Terminplaner bereits hinzugefügt',
	'DELETE_DATES'					=> 'Einzelne Datumsvorschläge löschen',
	'DELETE_HOOKUP'					=> 'Terminplaner löschen',
	'DELETE_HOOKUP_DELETE'			=> 'Alle Daten löschen',
	'DELETE_HOOKUP_DELETE_CONFIRM'	=> 'Willst du den Terminplaner wirklich vollständig löschen? Die Gespeicherten Daten (Benutzer, Datumsvorschläge, Verfügbarkeitsinformationen) gehen verloren und können nicht wiederhergestellt werden.',
	'DELETE_HOOKUP_DELETE_EXPLAIN'	=> 'Alle Daten dieses Terminplaners werden aus der Datenbank gelöscht.',
	'DELETE_HOOKUP_DISABLE'			=> 'Nur deaktivieren',
	'DELETE_HOOKUP_DISABLE_CONFIRM'	=> 'Willst du den Terminplaner wirklich deaktivieren? Die Gespeicherten Daten (Benutzer, Datumsvorschläge, Verfügbarkeitsinformationen) bleiben in der Datenbank gespeichert und der Terminplaner kann jederzeit wieder reaktiviert werden.',
	'DELETE_HOOKUP_DISABLE_EXPLAIN'	=> 'Der Terminplaner wird im Thema nicht mehr angezeigt, die gespeicherten Daten (Benutzer, Datumsvorschläge, Verfügbarkeitsinformationen) bleiben jedoch in der Datenbank gespeichert.',
	'DELETE_HOOKUP_NO'				=> 'Nichts löschen',
	'DELETE_USERS'					=> 'Einzelne Benutzer löschen',
	'DELETE_WHOLE_HOOKUP'			=> 'Gesamten Terminplaner löschen',

	'HIDE_ALL_DATES'				=> 'Terminliste verstecken',
	'HIDE_HOOKUP'					=> 'Terminplaner verstecken',
	'HOOKUP'						=> 'Terminplaner',
	'HOOKUP_ADD_DATEFORMAT'			=> ' (jjjj-mm-tt ss:mm)', //shown only for non js users (js users use the calendar)
	'HOOKUP_ADD_DATES'				=> 'Datumsvorschläge hinzufügen',
	'HOOKUP_ADD_DATES_EXPLAIN'		=> 'Hiermit fügst du neue Datumsvorschläge in die Liste ein. Gib pro Zeile ein Datum im Format TT.MM.JJJJ ss:mm oder JJJJ-MM-TT ss:mm ein.',
	'HOOKUP_ADD_GROUPS'				=> 'Gruppen einladen',
	'HOOKUP_ADD_GROUPS_EXPLAIN'		=> 'Hiermit kannst du komplette Gruppen in die Liste einfügen. Die Mitglieder der Gruppe werden einzeln in die Liste eingefügt, eine Mehrfachauswahl ist möglich.',
	'HOOKUP_ADD_USERS'				=> 'Benutzer einladen',
	'HOOKUP_DATEFORMAT'				=> 'd.m H:i', //this is used for the column headings so it should be short
	'HOOKUP_DATEFORMAT_CALENDAR'	=> '%d.%m.%Y %H:%M',
	'HOOKUP_DATEFORMAT_POST'		=> 'l, d.m.Y H:i', //this is used for the post when the active date is set
	'HOOKUP_DATEFORMAT_TITLE'		=> 'd.m.Y H:i', //this is used for the topic title
	'HOOKUP_DELETE_CONFIRM'			=> 'Willst du wirklich %d Datumsvorschläge und %d Benutzer löschen?',
	'HOOKUP_DELETE_EXPLAIN'			=> 'Hier kannst du einzelne Benutzer/Datumsvorschläge oder den kompletten Terminplaner löschen',
	'HOOKUP_DELETE_VIEWTOPIC_EXPLAIN'	=> 'Dieses Thema enthält bereits einen aktiven Terminplaner. Um den gesamten Terminplaner oder einzelne Benutzer/Datumsvorschläge zu löschen, verwende den Reiter <em>Löschen</em> in der Themenansicht',
	'HOOKUP_DESC'					=> 'Dieses Thema enthält einen Terminplaner, der dazu verwendet werden kann einen Termin mit anderen Usern abzusprechen.',
	'HOOKUP_INVITE_MYSELF'			=> 'Mich selbst einladen',
	'HOOKUP_INVITE_SELF'			=> 'Teilnehmen',
	'HOOKUP_INVITE_SELF_DESC'		=> 'Ja, ich möchte an diesem Terminplaner teilnehmen.',
	'HOOKUP_INVITE_SELF_EXPLAIN'	=> 'Dies ist ein offener Terminplaner, jeder Interessent kann sich selbst als Teilnehmer eintragen. Wenn du an diesem Termin teilnehmen möchtest, verwende dazu den folgenden Button.',
	'HOOKUP_INVITE_SELF_EXPLAIN_GUEST'	=> 'Dies ist ein offener Terminplaner, jeder Interessent kann sich selbst als Teilnehmer eintragen. Um diese Funktion nutzen zu können, musst du dich jedoch zuerst im Forum anmelden.',
	'HOOKUP_INVITE_SELF_LEAVE'			=> 'Teilnahme beenden',
	'HOOKUP_INVITE_SELF_LEAVE_CONFIRM'	=> 'Möchtest du deine Teilnahme an diesem Terminplaner wirklich beenden?',
	'HOOKUP_INVITE_SELF_LEAVE_DESC'		=> 'Klicke hier, um deine Teilnahme an diesem Terminplaner zu beenden.',
	'HOOKUP_INVITE_SELF_LEAVE_EXPLAIN'	=> 'Du bist derzeit ein Mitglied dieses Terminplaners. Verwende den folgenden Button, wenn du nicht mehr teilnehmen möchtest.',
	'HOOKUP_NO_DATES'				=> 'Es wurden noch keine Termine hinzugefügt.',
	'HOOKUP_NO_USERS'				=> 'Es wurden noch keine Benutzer eingeladen.',
	'HOOKUP_OVERVIEW'				=> 'Terminplaner-Übersicht',
	'HOOKUP_SELF_INVITE'			=> 'Selbsteinladung',
	'HOOKUP_SELF_INVITE_DESC'		=> 'Jeder Interessent darf sich selbst der Mitgliederliste hinzufügen',
	'HOOKUP_SELF_INVITE_EXPLAIN'	=> 'Wenn die Liste potentieller Teilnehmer sehr groß ist, aber vermutlich nur wenige tatsächlich Interesse haben, kann man mit dieser Option aktivieren, dass sich jeder Interessent selbst als Mitglied des Terminplaners eintragen darf.',
	'HOOKUP_STATUS'					=> array(HOOKUP_YES => 'Ja', HOOKUP_NO => 'Nein', HOOKUP_MAYBE => 'Evtl.', HOOKUP_UNSET => '-'),
	'HOOKUP_USER_EXISTS'			=> 'Der Benutzer %s ist bereits Mitglied des Terminplaners.',
	'HOOKUP_USERS_EXIST'			=> 'Alle Benutzer der gewählten Gruppen sind bereits Mitglied des Terminplaners.',

	'INVALID_DATE'			=> 'Ungültiges Datum. Das Datum muss im Format TT.MM.JJJJ SS:MM oder JJJJ-MM-TT SS:MM angegeben werden',

	'NO_DATE'				=> 'Datum existiert nicht!',

	'OPEN_CALENDAR'			=> 'Kalender öffnen',

	'SET_ACTIVE'				=> 'Festlegen',
	'SET_ACTIVE_CONFIRM'		=> 'Bist du sicher, dass du %s zum aktiven Datum machen möchtest?',
	'SET_ACTIVE_POST_REPLY'		=> 'Dem Thema einen neuen Beitrag mit Hinweis auf das aktive Datum hinzufügen',
	'SET_ACTIVE_POST_TEMPLATE'	=> 'Der Termin wurde festgelegt: [b]{ACTIVE_DATE}[/b]',
	'SET_ACTIVE_POST_TITLE'		=> 'Termin festgelegt: %s',
	'SET_ACTIVE_SEND_EMAIL'		=> 'Mitglieder des Terminplaners per E-Mail über aktives Datum informieren',
	'SET_ACTIVE_TITLE_PREFIX'	=> 'Aktives Datum dem Thementitel voranstellen',
	'SHOW_ALL_DATES'			=> 'Alle Termine zeigen',
	'SUM'						=> 'Summe',

	'UNSET_ACTIVE'				=> 'Termin neu verhandeln',
	'UNSET_ACTIVE_CONFIRM'		=> 'Bist du sicher, dass du das aktive Datum zurücksetzen und den Terminplaner wiedereröffnen möchtest?',
	'USER_CANNOT_READ_FORUM'	=> 'Der Benutzer %s hat keine Leseberechtigung für dieses Forum',
	'USERNAMES_EXPLAIN'			=> 'Hiermit kannst du neue Benutzer in die Liste einfügen. Du kannst mehrere User gleichzeitig eingeben, verwende für jeden User eine neue Zeile.',
));

// language vars for UMIL
$lang = array_merge($lang, array(
	'HOOKUP_MOD'					=> 'HookUp MOD',

	'INSTALL_HOOKUP_MOD'			=> 'HookUp MOD installieren',
	'INSTALL_HOOKUP_MOD_CONFIRM'	=> 'Bist du dir sicher, dass du die HookUp MOD installieren möchtest?',

	'UNINSTALL_HOOKUP_MOD'			=> 'HookUp MOD deinstallieren',
	'UNINSTALL_HOOKUP_MOD_CONFIRM'	=> 'Bist du dir sicher, dass du die HookUp MOD deinstallieren möchtest?',
	'UPDATE_HOOKUP_MOD'				=> 'HookUp MOD aktualisieren',
	'UPDATE_HOOKUP_MOD_CONFIRM'		=> 'Bist du dir sicher, dass du die HookUp MOD aktualisieren möchtest?',
));


?>