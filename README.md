# Lume-Dental

## System rezerwacji usług

Lume-Dental to aplikacja dla gabinetu stomatologicznego. Użytkownik może założyć konto i zalogować się, a administrator zarządza ofertą, personelem i rezerwacjami.

## Autorzy

- Marcel Maciak
- Julka Ignaczak

## Technologie

- PHP 8.1 lub nowszy
- HTML5, CSS3 i JavaScript
- MySQL lub MariaDB
- Git i GitHub

## Struktura projektu

```text
LUME-Dental-main/
├── app/                 # połączenie z bazą i wspólne widoki
├── css/                 # style strony
├── database/            # skrypt bazy i diagram ERD
├── images/              # materiały graficzne
├── js/                  # proste funkcje JavaScript
├── public/              # strony dostępne w przeglądarce
└── README.md
```

## Uruchomienie w XAMPP

1. Skopiuj folder projektu do `C:\xampp\htdocs`.
2. W XAMPP uruchom Apache i MySQL.
3. W phpMyAdmin utwórz bazę o nazwie `lume_dental`.
4. Wybierz tę bazę, otwórz kartę **Import** i zaimportuj plik `database/lume_dental.sql`.
5. Otwórz `http://localhost/LUME-Dental-main/public/` w przeglądarce.

Domyślne połączenie PHP jest ustawione na `127.0.0.1`, bazę `lume_dental`, użytkownika `root` i puste hasło (typowa instalacja XAMPP). Można je zmienić zmiennymi środowiskowymi:

| Zmienna | Wartość domyślna |
| --- | --- |
| `LUME_DB_HOST` | `127.0.0.1` |
| `LUME_DB_PORT` | `3306` |
| `LUME_DB_NAME` | `lume_dental` |
| `LUME_DB_USER` | `root` |
| `LUME_DB_PASSWORD` | puste hasło |

## Konta demonstracyjne

Poniższe konta są przeznaczone wyłącznie do lokalnego testowania po zaimportowaniu bazy:

| Rola | E-mail | Hasło |
| --- | --- | --- |
| Administrator | `admin@lume-dental.pl` | `Administrator123!` |
| Pracownik | `anna.nowak@lume-dental.pl` | `Pracownik123!` |
| Klient | `jan.kowalski@example.com` | `Klient123!` |

Hasła kont testowych są zapisane w bazie jako skróty. Nie używaj tych haseł ani domyślnego konta `root` na serwerze publicznym.
Powyższe hasła są ustawiane przez przykładowy plik SQL przy tworzeniu nowej bazy. Jeśli baza była już wcześniej zaimportowana, jej konta nie zostaną zmienione automatycznie.

## Funkcje

- Rejestracja i logowanie z bezpiecznym hashowaniem haseł.
- Trzy role: klient, pracownik i administrator.
- Edycja podstawowych danych profilu.
- Publiczna lista aktywnych usług.
- Panel administratora z liczbą klientów, pracowników, usług i rezerwacji.
- Dodawanie, edycja i usuwanie kategorii.
- Dodawanie i edycja usług, w tym ceny, czasu trwania i aktywności. Usługi używane przez rezerwacje można bezpiecznie dezaktywować.
- Tworzenie kont pracowników, edycja danych, przypisywanie wielu usług i ustawianie tygodniowych godzin pracy.
- Przeglądanie i filtrowanie rezerwacji po dacie, statusie, pracowniku lub kliencie; status można zmieniać z listy.
- Wyszukiwanie i stronicowanie list użytkowników, usług, kategorii i rezerwacji.
- Aktywowanie i dezaktywowanie kont. System nie pozwala wyłączyć własnego ani ostatniego aktywnego konta administratora.
- Ochrona panelu administratora po stronie PHP, tokeny CSRF w formularzach, zapytania przygotowane PDO i kodowanie danych w HTML.
- Responsywne tabele i formularze panelu.

## Baza danych

Plik `database/lume_dental.sql` tworzy przykładowe dane oraz tabele:

| Tabela | Zastosowanie |
| --- | --- |
| `users` | Konta, role i dane kontaktowe |
| `service_categories` | Kategorie oferty |
| `services` | Usługi, ceny i czas trwania |
| `employees` | Profil i aktywność pracownika |
| `employee_services` | Przypisanie wielu usług pracownikom |
| `employee_availability` | Tygodniowe godziny pracy |
| `reservations` | Rezerwacje klientów |

Plik `database/erd_lume_dental.png` przedstawia relacje między tabelami. Klucze obce chronią między innymi przed usunięciem kategorii lub usługi, która jest wykorzystywana w innych danych.
