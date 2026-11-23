<?php
/**
 * Language checks for the translatable strings (SET-RC-14). Run by `php bin/compile-translations.php --check`
 * and by tests/Unit/Support/TranslationsTest.php; standalone: `php bin/check-i18n-language.php`.
 *
 * (a) Source strings are English: no msgid in languages/review-captain.pot (which --check keeps in sync
 *     with the code) may contain a German letter (ä ö ü ß, except in all-caps words such as the
 *     confirmation word LÖSCHEN) or a common German word.
 * (b) One form of address per file: de_DE/de_AT ("du") must not address the reader with "Sie", and
 *     de_DE_formal/de_CH ("Sie") must not use "du".
 *
 * Heuristics and their limits:
 * - (a) only finds strings with an umlaut/ß, one of the listed words or a typical German ending
 *   (-ung, -heit, -keit, -lich, -schaft); a German string without them (e.g. "Status", "Name",
 *   "Export", "Layout") is indistinguishable from English. Words that are German and English at the
 *   same time ("die", "an", "so", "Tag") are not listed.
 * - (b) "du" files: flags "Ihnen" and "Ihr…" anywhere and "Sie" inside a sentence. A sentence-initial
 *   "Sie" is not flagged because it is usually "it"/"they" ("Sie wartet auf Moderation."); a formal
 *   sentence that starts with "Sie haben …" therefore slips through. A sentence-initial "Ihre" is
 *   flagged even when it means "their" – rephrase such a translation.
 * - (b) "Sie" files: flags du/dich/dir/dein…/euch/euer…, second-person verb forms (kannst, musst, hast
 *   …), a list of common du imperatives (Prüfe, Wähle, Gib, Lege …) and "Bitte" followed by a word
 *   ending in -e. Imperatives outside the list are not found, and imperatives spelled like nouns
 *   (Stelle, Teile, Versuche) are left out on purpose.
 */

/**
 * Minimal PO/POT reader: list of entries with ctx, id, plural, str (forms).
 *
 * @return list<array{ctx: ?string, id: string, plural: ?string, str: list<string>}>
 */
function rc_i18n_read_po( string $file ): array {
	$entries = array();
	$cur     = null;
	$field   = null;
	foreach ( array_merge( file( $file, FILE_IGNORE_NEW_LINES ), array( '' ) ) as $line ) {
		if ( '' === trim( $line ) ) {
			if ( null !== $cur && isset( $cur['id'] ) && '' !== $cur['id'] ) {
				$entries[] = $cur;
			}
			$cur   = null;
			$field = null;
			continue;
		}
		$cur ??= array( 'ctx' => null, 'plural' => null, 'str' => array() );
		if ( preg_match( '/^(msgctxt|msgid|msgid_plural|msgstr(?:\[(\d+)\])?) "(.*)"$/', $line, $m ) ) {
			$field = 'msgctxt' === $m[1] ? 'ctx' : ( 'msgid' === $m[1] ? 'id' : ( 'msgid_plural' === $m[1] ? 'plural' : (int) ( $m[2] ?? 0 ) ) );
			if ( is_int( $field ) ) {
				$cur['str'][ $field ] = stripcslashes( $m[3] );
			} else {
				$cur[ $field ] = stripcslashes( $m[3] );
			}
		} elseif ( str_starts_with( $line, '"' ) && null !== $field ) {
			if ( is_int( $field ) ) {
				$cur['str'][ $field ] .= stripcslashes( substr( $line, 1, -1 ) );
			} else {
				$cur[ $field ] .= stripcslashes( substr( $line, 1, -1 ) );
			}
		}
	}
	return $entries;
}

/** Reason why an English source string looks German, or null. */
function rc_i18n_german_source( string $text ): ?string {
	// Letters ä ö ü ß in a word that is not written in capitals (LÖSCHEN is a confirmation word).
	if ( preg_match_all( '/[\p{L}]*[äöüßÄÖÜ][\p{L}]*/u', $text, $m ) ) {
		foreach ( $m[0] as $word ) {
			if ( mb_strtoupper( $word ) !== $word ) {
				return 'German letter in "' . $word . '"';
			}
		}
	}
	$words = 'und|oder|nicht|der|das|den|dem|des|ein|eine|einen|einem|einer|ist|sind|wird|werden|wurde|wurden|mit|von|vom|für|auf|bei|nach|zum|zur|im|ab|bis|je|ja|nein|ohne|nur|bitte|noch|kein|keine|keiner|alle|neu|neue|neuer|neuen|anzeigen|ansehen|speichern|anlegen|fehlt|erstellen|herunterladen|aktiv|sie|du|dein|deine|ihre|ihr|ihnen|bewertung|bewertungen|einstellungen|datenschutz|erfahrungsberichte?|hinweise?|fehler|seite|zeit|datei|zeile|zeilen|profil|quelle|frage|antwort|sterne|tage|grund|bezug|umfang|titel|sprache|zeitraum|verbindung|abbrechen|erledigt|offen|benutzer|rolle';
	if ( preg_match( '/\b(' . $words . ')\b/iu', $text, $m ) ) {
		return 'German word "' . $m[1] . '"';
	}
	// Typical German endings (-ung, -heit, -keit, -lich, -schaft).
	if ( preg_match( '/\b\p{L}+(?:ungen|ung|heit|keit|lich|liche|lichen|schaft)\b/u', $text, $m ) && ! preg_match( '/^(?:[Yy]oung|[Hh]ung|[Ss]ung|[Ll]ung|[Rr]ung|[Ss]lung)$/', $m[0] ) ) {
		return 'German word "' . $m[0] . '"';
	}
	return null;
}

/** Reason why a translation breaks its form of address ("du" or "Sie"), or null. */
function rc_i18n_address_problem( string $text, string $form ): ?string {
	if ( 'du' === $form ) {
		if ( preg_match( '/\b(Ihnen|Ihr|Ihre|Ihren|Ihrem|Ihrer|Ihres)\b/u', $text, $m ) ) {
			return 'formal "' . $m[1] . '"';
		}
		// "Sie" inside a sentence (not after the start of the text, a sentence end, a colon, a line break or an opening quote).
		if ( preg_match( '/(?<![.!?:;„"\n]\s)(?<![.!?:;„"\n])(?<!^)\bSie\b/u', ltrim( $text ), $m, PREG_OFFSET_CAPTURE ) && $m[0][1] > 0 ) {
			return 'formal "Sie" inside a sentence';
		}
		return null;
	}
	if ( preg_match( '/\b(du|Du|dich|Dich|dir|Dir|dein|deine|deinen|deinem|deiner|deines|Dein|Deine|Deinen|Deinem|Deiner|Deines|euch|Euch|euer|eure|euren|eurem|eurer)\b/u', $text, $m ) ) {
		return 'informal "' . $m[1] . '"';
	}
	if ( preg_match( '/\b(kannst|musst|hast|bist|willst|möchtest|darfst|solltest|wirst|siehst|gibst|findest|brauchst|bekommst|erhältst|weißt|kennst|tippst|speicherst|legst|wählst)\b/u', $text, $m ) ) {
		return 'informal verb "' . $m[1] . '"';
	}
	// Common du imperatives (nouns with the same spelling such as Stelle, Teile, Versuche are left out).
	if ( preg_match( '/\b(Prüfe|prüfe|Wähle|wähle|Gib|gib|Lege|lege|Füge|füge|Richte|richte|Mach|mach|Lass|lass|Tippe|tippe|Hake|hake|Übernimm|übernimm|Sammle|sammle|Zeige|zeige|Bestätige|bestätige|Bewerte|bewerte|Exportiere|exportiere|Importiere|importiere|Lade|lade|Hinterlege|hinterlege|Beschreibe|beschreibe|Deaktiviere|deaktiviere|Aktiviere|aktiviere|Verwende|verwende|Nutze|nutze|Speichere|speichere|Kopiere|kopiere|Öffne|öffne|Klicke|klicke|Trage|trage|Ersetze|ersetze|Entferne|entferne|Erstelle|erstelle|Verlinke|verlinke|Gehe|gehe|Schreibe|schreibe|Sende|sende|Ergänze|ergänze|Wechsle|wechsle|Verwalte|verwalte|Bearbeite|bearbeite|Formuliere|formuliere|Kürze|kürze|Fordere|fordere|Achte auf|achte auf|Stelle (?:\S+ ){1,4}sicher|stelle (?:\S+ ){1,4}sicher)\b/u', $text, $m ) ) {
		return 'informal imperative "' . $m[1] . '"';
	}
	if ( preg_match( '/\b[Bb]itte\s+(gib|nimm|lass|lies|sieh|hilf|wirf|(?!(?:die|diese|eine|keine|alle|ihre|seine|nicht|nur|gerne|beide)\b)[a-zäöü]{3,}e)\b(?!\s+Sie\b)/u', $text, $m ) ) {
		return 'informal imperative "Bitte ' . $m[1] . '"';
	}
	return null;
}

/**
 * All problems: English source strings and the form of address per translation file.
 *
 * @return list<string>
 */
function rc_i18n_language_problems( string $root ): array {
	$problems = array();
	foreach ( rc_i18n_read_po( $root . '/languages/review-captain.pot' ) as $e ) {
		foreach ( array( $e['id'], (string) $e['plural'] ) as $text ) {
			$reason = '' !== $text ? rc_i18n_german_source( $text ) : null;
			if ( null !== $reason ) {
				$problems[] = "source string is not English ($reason): " . mb_substr( $text, 0, 70 );
			}
		}
	}
	$forms = array(
		'de_DE'        => 'du',
		'de_AT'        => 'du',
		'de_DE_formal' => 'Sie',
		'de_CH'        => 'Sie',
	);
	foreach ( $forms as $locale => $form ) {
		$file = $root . "/languages/review-captain-$locale.po";
		if ( ! is_file( $file ) ) {
			$problems[] = "$locale: translation file missing";
			continue;
		}
		foreach ( rc_i18n_read_po( $file ) as $e ) {
			foreach ( $e['str'] as $text ) {
				$reason = rc_i18n_address_problem( $text, $form );
				if ( null !== $reason ) {
					$problems[] = "$locale (\"$form\"): $reason: " . mb_substr( str_replace( "\n", ' ', $text ), 0, 70 );
				}
			}
		}
	}
	return $problems;
}

if ( isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	$problems = rc_i18n_language_problems( dirname( __DIR__ ) );
	if ( $problems ) {
		fwrite( STDERR, implode( PHP_EOL, $problems ) . PHP_EOL . count( $problems ) . " language problem(s)\n" );
		exit( 1 );
	}
	echo "Source strings English, de_DE/de_AT \"du\", de_DE_formal/de_CH \"Sie\"\n";
}
