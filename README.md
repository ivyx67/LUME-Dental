# Lume-Dental

## System rezerwacji usług

Projekt systemu rezerwacji usług dla gabinetu stomatologicznego **Lume-Dental**.

System ma umożliwiać zarządzanie usługami, pracownikami oraz rezerwacjami wizyt. 
---

## Autorzy

* Marcel Maciak
* Julka Ignaczak

---

## Technologie

Projekt wykorzystuje:

* **PHP** – logika aplikacji
* **HTML5** – struktura stron
* **CSS3** – wygląd i stylowanie
* **JavaScript** – funkcjonalności po stronie klienta
* **MySQL / MariaDB** – baza danych
* **Git / GitHub** – kontrola wersji i współpraca

---

## Struktura projektu

Aktualna struktura projektu:

```text
Lume-Dental/
├── database/
│   └── lumedental.sql
├── public/
├── css/
├── js/
├── images/
└── README.md
```

Plik `database/lumedental.sql` zawiera skrypt odpowiedzialny za utworzenie bazy danych, tabel, relacji oraz przykładowych danych testowych.

## Baza danych

Baza danych systemu obejmuje między innymi:

* użytkowników,
* kategorie usług,
* usługi,
* pracowników,
* przypisanie pracowników do usług,
* dostępność pracowników,
* rezerwacje.

W projekcie zastosowano relacje między tabelami oraz klucze główne i obce.

Relacja między pracownikami i usługami jest relacją **wiele-do-wielu (N:M)** i jest realizowana za pomocą tabeli `employee_services`.

### Główne tabele

| Tabela                  | Opis                              |
| ----------------------- | --------------------------------- |
| `users`                 | Użytkownicy systemu               |
| `service_categories`    | Kategorie usług                   |
| `services`              | Usługi oferowane przez gabinet    |
| `employees`             | Pracownicy                        |
| `employee_services`     | Powiązanie pracowników z usługami |
| `employee_availability` | Dostępność pracowników            |
| `reservations`          | Rezerwacje wizyt                  |

---

## Uruchomienie bazy danych

Do utworzenia bazy danych należy wykorzystać plik:

```text
database/lumedental.sql
```

Skrypt należy uruchomić w środowisku obsługującym **MySQL lub MariaDB**, np. za pomocą:

* phpMyAdmin,
* MySQL Workbench,
* konsoli MySQL/MariaDB.

Po wykonaniu skryptu zostanie utworzona baza danych wraz z tabelami, relacjami oraz danymi testowymi.

---

## Dane testowe

W bazie znajdują się przykładowe dane umożliwiające testowanie struktury systemu.

> Dane logowania do aplikacji zostaną uzupełnione wraz z implementacją systemu logowania.

---

## Kontrola wersji

Projekt jest rozwijany z wykorzystaniem **Git oraz GitHub**.




