Aplikacja grafik call center
==

# Kontekst aplikacji

1. Mamy Call Center w jakiejś firmie telekomunikacyjnej, obsługujące różne “kolejki” tematyczne (np. sprzedaż, wsparcie techniczne, reklamacje itd)
2. Pracuje tam kilkadziesiąt osób w elastycznych godzinach pracy, dostępność ludzie deklarują z tygodniowym wyprzedzeniem, ale jest ona zmienna i może się zmienić po wygenerowaniu grafiku
3. Trudno jest układać grafik dla tych agentów, by pokryć zapotrzebowanie a jednocześnie, żeby nie było za dużo ich w ramach jednej godziny.
4. Różni agenci umieją obsługiwać różne kolejki (niektórzy wiele różnych kolejek, niektórzy 1-2 kolejki)
5. Każdy agent na danej kolejce ma swoją efektywność, bazującą na danych historycznych.
6. Znamy historię połączeń w minionych tygodniach na każdej kolejce, w każdej godzinie, celem robienia predykcji

# Proces układania grafika na następny tydzień

Zakładamy, że:
1. Pracownicy układają swoją dostępność z tygodniowym wyprzedzeniem
2. Zgłoszenia przychodzą na bieżąco i staramy się je jak najszybciej obsłużyć.
3. W rozmowie telefonicznej może być poruszony temat który jeszcze nie ma ticketa.
4. Pracownicy priorytetyzują odpowiadanie na bieżące telefony ponad to co mają na grafiku zaplanowane.
Tzn. jeśli pracownik nad czymś pracuje i zadzwoni telefon i nie ma innego wolnego pracownika który by mógł odebrać telefon, to czasem przerwie bieżącą pracę i odbierze telefon.


# Moduły aplikacji na backendzie
Backend w Symfony, PHP. DB w MySQL.
Zakładamy możliwość stworzenia rozproszonego systemu w przyszłości.

## Moduł kategorii
Zawiera różne kategorie (np. sprzedaż, wsparcie techniczne, reklamacje itd). Narazie będą zhardkodowane.
Zawiera domyślny czas rozwiązania ticketa per kategoria w minutach.

## Moduł klientów
Klienci to są te osoby, które zgłaszają tickety do systemu.
Klienci nie koniecznie muszą być autoryzowani (mogą być anonimowi ze strony internetowej, jeszcze nie zidentyfikowani z rozmowy telefonicznej)

## Moduł kolejki ticketów
Zawiera tickety i ich historię. Historia zawiera faktyczny czas spędzony na danym tickecie w przeszłości przez pracowników (czas rozmowy telefonicznej).
Ticket jest przypisany do kategorii i klienta.
Zawiera status ticketa czyli czy jest zamknięty, czy oczekuje na odpowiedź z naszej strony, czy oczekuje na odpowiedź ze strony klienta.
Może policzyć efektywność pracownika.
Ticket może zawierać czas rozpoczęcia jego obsługi bez czasu zakończenia (czyli ticket jest w toku).

## Moduł autoryzacji
Pracownik przegląda i odpisuje na tickety tych kategorii do których jest przypisany.
Ten moduł autoryzuje pracownika do dostępu do kategorii.

## Moduł dostępności pracownika
Zawiera dostępność pracownika - w których dniach i godzinach jest dostępny.

## Moduł grafika pracownika
Zawiera przypisanie pracownika do ticketa w danym dniu.
Zawiera możliwość automatycznego przypisania ticketów do pracowników na podstawie ich efektywności i domyślnego czasu na ticket.

## Moduł autentykacji pracowników
Rejestrowanie, logowanie pracowników

# Moduły aplikacji na front-endzie
Frontend w react.js.

## Moduły bez autentykacji

### Moduł dodawania ticketów przez klientow
Formularz do dodawania ticketa

### Moduł odpowiadania na tickety przez klientów
Jest to w chat uruchamiany automatycznie po utworzeniu nowego ticketa przez klienta.
Na bieżąco dostanie nowe odpowiedzi od strony pracowników (web socket).

### Moduł logowania się do aplikacji przez pracowników
Formularz do logowania się

### Moduł dodawania pracowników
Formularz rejestrowania pracownika (login i hasło), checkboxy kategorii do których ma uprawnienia.

## Moduły pracowników po autentykacji

### Moduł grafika pracownika
Główne centrum pracy dla pracownika, pierwsza strona którą zobaczy.
Zawiera najbliższe 7 dni.
Pokazuje przypisane tickety i czas na nich spędzony dzisiaj.
Umożliwia dodanie czasu spędzonego na rozmowie telefonicznej.
Umożliwia zmianę statusu ticketa na 'w toku' lub 'oczekujący'.
Jeśli ticket ma status 'w toku', to jego czas jest rejestrowany.
Posiada duży przycisk 'odbieram telefon' który uruchomi moduł 'odbieram telefon'.

Posiada status bar i w nim ostrzeżenia jeśli pracownik ma za mało lub za dużo pracy.
Posiada na górze strony sekcję z ticketem 'w toku' i możliwością dodawania notatek do tego ticketa.

### Moduł 'odbieram telefon'
Ten moduł reprezentuje odebranie telefonu, jeszcze nie wiemy w jakiej sprawie i od kogo on jest.
W momencie odebrania telefonu aplikacja zacznie rejestrować czas. Przerwie rejestrowanie czasu innych ticketów, przestawi je na status 'oczekujący'.
W module jest możliwość wyszukania istniejącego ticketa, a także stworzenia nowego ticketa.
Po wybraniu istniejącego ticketa lub stworzeniu nowego, umożliwia dodanie notatek do tego ticketa.
Po zakończeniu połączenia pracownik klika 'Zakończyłem połączenie', a aplikacja zarejestruje czas połączenia do wybranego ticketa.
Po zakończeniu połączenia, nowostworzony/wybrany ticket jest automatycznie dodawany do grafika bieżącego dnia a jego status jest oznaczany na 'w toku' (a jego czas rejestrowany). W tym momencie pracownik ma czas żeby wykonać operacje związane z rozmową którą właśnie przeprowadził.
Jeśli żaden nowy ticket nie został wybrany podczas rozmowy, ticket sprzed rozmowy jest ustawiany jako 'w toku'.

### Moduł przypisania/planowania ticketów
Zawiera backlog ticketów spośród kategorii.
Umożliwia ręczne przypisanie ticketów na poszczególne dostępne dni.
Pokazuje przewidywaną ilość ticketów którą pracownik może obsłużyć danego dnia.
Umożliwia automatyczne dopisanie ticketów do wszystkich dni na podstawie przewidywanej ilości obsługiwanych ticketów.

### Moduł ustawiania dostępności
Pokazuje najbliższe 7 dni.
Umożliwia ustawienie godzin w których się jest dostępnym w poszczególnych dniach.

### Moduł monitoringu dla kierownika
Pokazuje w danym dniu ogólne statystyki obciążenia pracowników.
Pokazuje ilość oczekujących ticketów w kolejkach.
Możliwość włączenia automatycznego przypisywania zadań dla pracowników i kolejek.