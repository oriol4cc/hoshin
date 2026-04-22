# Hoshin webapp (PHP + SQL + JavaScript)

## Requisits
- PHP 8.1+
- MySQL 8+ (o MariaDB compatible)

## Configuració
1. Crea la base de dades executant `sql/schema.sql`.
2. Configura credencials amb variables d'entorn (opcional):
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

## Executar en local
```bash
cd public
php -S localhost:8000
```

Obre: http://localhost:8000

## Funcionalitats incloses
- Crear, eliminar i reordenar objectius.
- Crear subobjectius.
- Crear, editar i eliminar cards dins cada cel·la.
- Moure cards entre cel·les amb drag & drop.
- Les cards de **Yearly goals** requereixen un **codi únic**.
- Les cards de la resta de columnes han d'estar vinculades a un codi yearly seleccionat en un dropdown.


## Compatibilitat de dades existents
- Si la taula `cards` ja existia d'una versió anterior, l'aplicació afegeix automàticament les columnes `code` i `linked_yearly_code` en el primer arrenc.
