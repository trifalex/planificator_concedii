# Planificator Concedii

Aplicatie PHP pentru gestionarea concediilor angajatilor, cu vizualizare calendar tip Excel si detectie automata a suprapunerilor pe departamente.

## Functionalitati

- **Calendar anual** - vizualizare tip Excel cu toate zilele anului pe o singura linie per angajat
- **Sarbatori legale Romania** - incluse automat (Anul Nou, Paste Ortodox, Rusalii, Craciun, etc.)
- **Departamente** - organizare angajati pe departamente cu detectie suprapuneri per departament
- **Detectie suprapuneri** - evidentiaza automat cand 2+ angajati din acelasi departament au concediu simultan
- **Filtrare** - vizualizare pe departament sau toti angajatii
- **Import bulk** - adaugare rapida lista angajati
- **Export JSON** - download date pentru backup sau integrari
- **Salvare automata** - datele se salveaza in fisiere JSON separate pe an
- **Fara baza de date** - functioneaza doar cu fisiere JSON

## Instalare

1. Copiaza `planificator_concedii.php` pe serverul tau web (Apache/Nginx cu PHP)
2. Asigura-te ca folderul are permisiuni de scriere:
   ```bash
   chmod 755 /calea/catre/folder/
   ```
3. Acceseaza in browser: `https://domeniul-tau.ro/planificator_concedii.php`

## Cerinte

- PHP 7.4+ 
- Extensia `calendar` pentru PHP (pentru calcul zile/luna)
- Permisiuni scriere in folderul aplicatiei

## Utilizare

### Adaugare angajat
1. Introdu numele in campul "Nume angajat"
2. Selecteaza departamentul
3. Seteaza numarul de zile de concediu (default: 21)
4. Click "Adauga"

### Import lista angajati
1. Click "Import"
2. Selecteaza departamentul pentru toti angajatii importati
3. Introdu numele (unul pe linie)
4. Click "Importa"

### Setare concediu
- Click pe o zi din calendar pentru a marca/demarca concediu
- Nu se pot selecta weekend-uri si sarbatori legale
- Zilele de concediu apar in albastru
- Suprapunerile (acelasi departament) apar in rosu

### Filtrare
- Selecteaza un departament din dropdown-ul "Filtru" pentru a vedea doar angajatii din acel departament
- Selecteaza "Toate departamentele" pentru vizualizare completa

## Structura fisiere

```
/folder/
  planificator_concedii.php    # Aplicatia principala
  concedii_2024.json           # Date pentru anul 2024
  concedii_2025.json           # Date pentru anul 2025
  concedii_2026.json           # Date pentru anul 2026
  ...
```

## Format JSON

```json
{
  "year": 2026,
  "users": [
    {
      "name": "Ion Popescu",
      "department": "IT",
      "totalDays": 21,
      "vacations": ["2026-03-02", "2026-03-03", "2026-03-04"]
    },
    {
      "name": "Maria Ionescu",
      "department": "HR",
      "totalDays": 21,
      "vacations": ["2026-07-15", "2026-07-16"]
    }
  ]
}
```

## Culori

| Culoare | Semnificatie |
|---------|--------------|
| Mov | Weekend (Sambata/Duminica) |
| Verde | Sarbatoare legala |
| Albastru | Zi de concediu |
| Rosu | Suprapunere (2+ persoane din acelasi departament) |

## Sarbatori legale incluse

- 1-2 Ianuarie - Anul Nou
- 6 Ianuarie - Boboteaza
- 7 Ianuarie - Sf. Ioan Botezatorul
- 24 Ianuarie - Ziua Unirii
- Vinerea Mare, Paste, Paste (a doua zi) - calculate automat
- 1 Mai - Ziua Muncii
- 1 Iunie - Ziua Copilului
- Rusalii (2 zile) - calculate automat
- 15 August - Adormirea Maicii Domnului
- 30 Noiembrie - Sfantul Andrei
- 1 Decembrie - Ziua Nationala
- 25-26 Decembrie - Craciunul

## Departamente default

- General
- IT
- HR
- Vanzari
- Marketing
- Financiar
- Productie
- Logistica

Departamentele noi se adauga automat cand importi angajati cu departamente diferite.

## License

MIT License - foloseste liber in proiecte personale sau comerciale.
