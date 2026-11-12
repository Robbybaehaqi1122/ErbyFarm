# Support

Shared infrastructure yang tidak dimiliki oleh satu business module:

- base controller / response helpers
- traits
- DTO
- enums
- query filters
- utility lintas modul

Jangan menaruh domain model atau feature service di sini. Jika sebuah class hanya
dipakai oleh satu modul, letakkan di modul tersebut.
