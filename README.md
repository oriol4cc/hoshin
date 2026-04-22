# Hoshin webapp (reinici net)

## Requisits
- PHP 8.1+
- MySQL 8+ o MariaDB

## Instal·lació
1. Executa `sql/schema.sql`.
2. (Opcional) configura variables:
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`

## Execució
```bash
cd public
php -S localhost:8000
```

Obre: http://localhost:8000

## Funcions
- Crear, eliminar i reordenar objectius i subobjectius.
- Crear, editar, eliminar i moure cards amb drag&drop.
- Yearly goals: codi únic obligatori.
- Resta de columnes: cal seleccionar un codi yearly vinculat.

## Nota important
Si venies d'una versió anterior que no funcionava bé, elimina i recrea la BD amb `sql/schema.sql` abans de provar aquest reinici.
