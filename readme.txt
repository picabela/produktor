=== Produktor WP ===
Contributors: bziku
Tags: wordpress, multisite, bulk, publish, management
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Panel zarządzania wieloma stronami WordPress z możliwością hurtowego dodawania artykułów i zarządzania publikacjami.

== Description ==

Produktor WP to zaawansowana wtyczka WordPress umożliwiająca zarządzanie wieloma zewnętrznymi stronami WordPress z jednego centralnego panelu. Główne funkcjonalności:

**Kluczowe funkcje:**
* Dodawanie i zarządzanie zewnętrznymi stronami WordPress
* Hurtowe publikowanie artykułów na wielu stronach
* Automatyczne mapowanie kategorii i tagów
* System kontroli duplikatów
* Szczegółowe logi i raportowanie
* Archiwum wykorzystanych artykułów
* Nowoczesny, responsywny interfejs

**Zarządzanie stronami:**
* Łączenie z zewnętrznymi stronami poprzez REST API
* Test połączenia przed dodaniem strony
* Monitoring statusu połączeń
* Synchronizacja danych ze stron zewnętrznych

**Publikowanie artykułów:**
* Publikowanie istniejących artykułów na wybrane strony
* Tworzenie nowych artykułów bezpośrednio w panelu
* Rozłożenie dat publikacji w zadanym przedziale czasowym
* Wybór autora dla publikowanych artykułów
* Obsługa kategorii, tagów i obrazów wyróżniających

**Mapowanie taksonomii:**
* Automatyczne mapowanie kategorii między stronami
* Ręczne zarządzanie mapowaniami
* Tworzenie nowych kategorii na stronach docelowych
* Reguły fallback dla nieistniejących kategorii

**Monitoring i raportowanie:**
* Szczegółowe logi wszystkich operacji
* Monitor błędów z filtrowaniem
* Raporty publikacji per strona
* Eksport danych do CSV

== Installation ==

1. Prześlij folder `produktor-wp` do katalogu `/wp-content/plugins/`
2. Aktywuj wtyczkę w panelu administratora WordPress
3. Przejdź do "Produktor WP" w menu administratora
4. Dodaj pierwszą stronę zewnętrzną i rozpocznij zarządzanie

== Frequently Asked Questions ==

= Czy wtyczka jest bezpieczna? =

Tak, wszystkie połączenia używają uwierzytelniania REST API WordPress. Dane logowania są szyfrowane i przechowywane bezpiecznie.

= Ile stron mogę dodać? =

Nie ma limitu liczby stron. Wtyczka została zoptymalizowana pod kątem wydajności.

= Czy mogę cofnąć publikację? =

Aktualnie wtyczka nie obsługuje cofania publikacji. Można ją jednak rozszerzyć o tę funkcjonalność.

== Changelog ==

= 1.0.0 =
* Pierwsza wersja wtyczki
* Zarządzanie stronami zewnętrznymi
* Hurtowe publikowanie artykułów
* System mapowania taksonomii
* Logi i raportowanie
* Archiwum artykułów

== Upgrade Notice ==

= 1.0.0 =
Pierwsza wersja wtyczki Produktor WP.

== Screenshots ==

1. Dashboard z przeglądem stron i statystyk
2. Lista stron zewnętrznych z opcjami zarządzania
3. Panel hurtowego dodawania artykułów
4. System mapowania kategorii
5. Logi i monitoring błędów

== Support ==

Wsparcie techniczne dostępne na GitHub: https://github.com/bziku/produktor-wp