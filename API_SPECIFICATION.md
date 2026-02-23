# API Specification / Спецификация API

Полная спецификация API на основе анализа скрипта `index.php`.

## Оглавление / Table of Contents

- [Обзор / Overview](#обзор--overview)
- [Аутентификация / Authentication](#аутентификация--authentication)
- [DML команды / DML Commands](#dml-команды--dml-commands-_m_)
- [DDL команды / DDL Commands](#ddl-команды--ddl-commands-_d_)
- [Утилитарные команды / Utility Commands](#утилитарные-команды--utility-commands)
- [Коды ошибок / Error Codes](#коды-ошибок--error-codes)
- [Рекомендуемые названия / Recommended Naming](#рекомендуемые-названия--recommended-naming)

---

## Обзор / Overview

API работает по REST-подобному принципу с использованием URL-маршрутизации:

```
GET/POST /{database}/{action}/{id}?params...
```

Где:
- `{database}` - имя базы данных (таблицы)
- `{action}` - название команды
- `{id}` - ID объекта (опционально)
- `params` - дополнительные параметры

### Базовые типы данных

| ID | Тип | Описание |
|----|-----|----------|
| 3 | SHORT | Короткий текст |
| 8 | CHARS | Символы |
| 9 | DATE | Дата (формат YYYYMMDD) |
| 13 | NUMBER | Целое число |
| 14 | SIGNED | Число со знаком |
| 11 | BOOLEAN | Логическое значение |
| 12 | MEMO | Длинный текст |
| 4 | DATETIME | Дата и время (Unix timestamp) |
| 10 | FILE | Файл |
| 2 | HTML | HTML-контент |
| 7 | BUTTON | Кнопка |
| 6 | PWD | Пароль (хешируется) |
| 5 | GRANT | Права доступа |
| 15 | CALCULATABLE | Вычисляемое поле |
| 16 | REPORT_COLUMN | Колонка отчета |
| 17 | PATH | Путь к файлу |

### Системные типы

| ID | Константа | Описание |
|----|-----------|----------|
| 18 | USER | Пользователь |
| 271 | DATABASE | База данных |
| 30 | PHONE | Телефон |
| 40 | XSRF | XSRF токен |
| 41 | EMAIL | Email |
| 42 | ROLE | Роль |
| 124 | ACTIVITY | Активность |
| 20 | PASSWORD | Пароль |
| 125 | TOKEN | Токен авторизации |
| 130 | SECRET | Секретный ключ |
| 22 | REPORT | Отчет |

---

## Аутентификация / Authentication

### Методы аутентификации

1. **Cookie Token** - токен в cookie с именем базы данных
2. **Header Token** - `Authorization: Bearer {token}` или `X-Authorization: {token}`
3. **Basic Auth** - `Authorization: Basic {base64(login:password)}`
4. **POST Token** - параметр `token` в теле запроса
5. **Secret Token** - параметр `secret` для одноразовой авторизации

### Получение токена

```http
GET /{db}/xsrf
```

**Ответ:**
```json
{
  "_xsrf": "xsrf_token_value",
  "token": "auth_token_value",
  "user": "username",
  "role": "role_name",
  "id": "user_id",
  "msg": ""
}
```

### XSRF защита

Для модифицирующих операций требуется передача XSRF-токена:
- В POST: параметр `_xsrf`
- В заголовке (для API)

---

## DML команды / DML Commands (`_m_*`)

DML (Data Manipulation Language) команды для работы с данными объектов.

### 1. Создание объекта / Create Object

**Команда:** `_m_new`

**URL:**
```http
POST /{db}/_m_new/{type_id}?up={parent_id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `up` | int | ID родительского объекта (1 для корневых) |
| `t{type_id}` | string | Значение объекта |
| `t{req_id}` | string | Значения реквизитов |
| `NEW_{req_id}` | string | Создание нового связанного объекта |

**Ответ (JSON API):**
```json
{
  "id": 123,
  "obj": 123,
  "ord": 1,
  "next_act": "edit_obj",
  "args": "new1=1",
  "val": "Object Value"
}
```

---

### 2. Сохранение объекта / Save Object

**Команда:** `_m_save`

**URL:**
```http
POST /{db}/_m_save/{id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `t{type_id}` | string | Значение объекта |
| `t{req_id}` | string | Значения реквизитов |
| `copybtn` | any | Флаг копирования объекта |
| `SEARCH_{req_id}` | string | Фильтр для справочников |
| `NEW_{req_id}` | string | Создание нового связанного объекта |

**Ответ:**
```json
{
  "id": 123,
  "obj": 123,
  "next_act": "object",
  "args": "saved1=1&F_U=1&F_I=123"
}
```

---

### 3. Установка значения реквизита / Set Attribute Value

**Команда:** `_m_set`

**URL:**
```http
POST /{db}/_m_set/{object_id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `t{req_id}` | string/int | Значение реквизита (для ссылок - ID) |

**Примеры:**
```
POST /mydb/_m_set/123
t456=new_value           # Установка значения реквизита 456
t789=42                  # Установка ссылки на объект 42
```

---

### 4. Удаление объекта / Delete Object

**Команда:** `_m_del`

**URL:**
```http
POST /{db}/_m_del/{id}
```

**Ограничения:**
- Нельзя удалить метаданные (объекты с `up=0`)
- Нельзя удалить объект, на который есть ссылки
- Нельзя удалить текущего пользователя

**Ответ:**
```json
{
  "id": 123,
  "obj": 123,
  "next_act": "object"
}
```

---

### 5. Перемещение объекта / Move Object

**Команда:** `_m_move`

**URL:**
```http
POST /{db}/_m_move/{id}?up={new_parent_id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `up` | int | ID нового родительского объекта |

---

### 6. Перемещение вверх в списке / Move Up in Order

**Команда:** `_m_up`

**URL:**
```http
POST /{db}/_m_up/{id}
```

Меняет местами объект с предыдущим в порядке сортировки.

---

### 7. Установка порядка / Set Order

**Команда:** `_m_ord`

**URL:**
```http
POST /{db}/_m_ord/{id}?order={new_order}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `order` | int | Новый порядковый номер (>= 1) |

---

### 8. Изменение ID объекта / Change Object ID

**Команда:** `_m_id`

**URL:**
```http
POST /{db}/_m_id/{id}?new_id={new_id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `new_id` | int | Новый ID объекта |

**Ограничения:**
- ID не должен быть занят
- Нельзя изменить ID метаданных

---

## DDL команды / DDL Commands (`_d_*`)

DDL (Data Definition Language) команды для работы с метаданными (типами).

> **Требования:** Права `WRITE` на редактирование типов (Types Grant)

### 1. Создание нового типа / Create New Type

**Старое имя:** `_d_new`
**Рекомендуемое имя:** `terms` (POST)

**URL:**
```http
POST /{db}/_d_new
POST /{db}/terms  # альтернативное имя
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `val` | string | Название типа |
| `t` | int | ID базового типа (0-17) |
| `unique` | int | 1 = значения должны быть уникальны |

**Ответ:**
```json
{
  "id": 456,
  "obj": 456,
  "next_act": "edit_types"
}
```

---

### 2. Сохранение типа / Save Type

**Старое имя:** `_d_save`
**Рекомендуемое имя:** `patchterm` (PATCH)

**URL:**
```http
POST /{db}/_d_save/{id}
PATCH /{db}/terms/{id}  # RESTful альтернатива
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `val` | string | Новое название типа |
| `t` | int | ID базового типа |
| `unique` | int | Флаг уникальности |

---

### 3. Удаление типа / Delete Type

**Старое имя:** `_d_del`
**Рекомендуемое имя:** `deleteterm` (DELETE)

**URL:**
```http
POST /{db}/_d_del/{id}
DELETE /{db}/terms/{id}  # RESTful альтернатива
```

**Ограничения:**
- Нельзя удалить тип, если существуют его экземпляры
- Нельзя удалить тип, используемый в отчетах
- Нельзя удалить тип, используемый в ролях

---

### 4. Добавление реквизита к типу / Add Attribute to Type

**Старое имя:** `_d_req`
**Рекомендуемое имя:** `attributes` (POST)

**URL:**
```http
POST /{db}/_d_req/{type_id}?t={attribute_type_id}
POST /{db}/attributes/{type_id}?t={attribute_type_id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `t` | int | ID типа добавляемого реквизита |
| `multiselect` | any | Флаг множественного выбора (для ссылок) |

---

### 5. Удаление реквизита / Delete Attribute

**Старое имя:** `_d_del_req`
**Рекомендуемое имя:** `deletereq` (DELETE)

**URL:**
```http
POST /{db}/_d_del_req/{attribute_id}
DELETE /{db}/attributes/{attribute_id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `forced` | any | Принудительное удаление с данными |

**Ограничения:**
- Нельзя удалить реквизит, используемый в отчетах
- Нельзя удалить реквизит, используемый в ролях
- При наличии данных требуется `forced=1`

---

### 6. Создание ссылочного типа / Create Reference Type

**Старое имя:** `_d_ref`
**Рекомендуемое имя:** `references` (POST)

**URL:**
```http
POST /{db}/_d_ref/{type_id}
POST /{db}/references/{type_id}
```

Создает пустой ссылочный тип для указанного объектного типа.

---

### 7. Установка псевдонима / Set Alias

**Старое имя:** `_d_alias`
**Рекомендуемое имя:** `setalias`

**URL:**
```http
POST /{db}/_d_alias/{attribute_id}?val={alias}
POST /{db}/setalias/{attribute_id}?val={alias}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `val` | string | Псевдоним (alias) для реквизита |

---

### 8. Переключение NOT NULL / Toggle NOT NULL

**Старое имя:** `_d_null`
**Рекомендуемое имя:** `setnull`

**URL:**
```http
POST /{db}/_d_null/{attribute_id}
POST /{db}/setnull/{attribute_id}
```

Переключает флаг обязательности реквизита (NOT NULL).

---

### 9. Переключение множественного выбора / Toggle Multi-select

**Старое имя:** `_d_multi`
**Рекомендуемое имя:** `setmulti`

**URL:**
```http
POST /{db}/_d_multi/{attribute_id}
POST /{db}/setmulti/{attribute_id}
```

Переключает флаг множественного выбора для ссылочных реквизитов.

---

### 10. Установка модификаторов / Set Modifiers

**Старое имя:** `_d_attrs`
**Рекомендуемое имя:** `modifiers`

**URL:**
```http
POST /{db}/_d_attrs/{attribute_id}
POST /{db}/modifiers/{attribute_id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `val` | string | Значение модификаторов |
| `alias` | string | Псевдоним |
| `set_null` | any | Флаг NOT NULL |
| `multi` | any | Флаг множественного выбора |

---

### 11. Перемещение реквизита вверх / Move Attribute Up

**Старое имя:** `_d_up`
**Рекомендуемое имя:** `moveup`

**URL:**
```http
POST /{db}/_d_up/{attribute_id}
POST /{db}/moveup/{attribute_id}
```

---

### 12. Установка порядка реквизита / Set Attribute Order

**Старое имя:** `_d_ord`
**Рекомендуемое имя:** `setorder`

**URL:**
```http
POST /{db}/_d_ord/{attribute_id}?order={new_order}
POST /{db}/setorder/{attribute_id}?order={new_order}
```

---

## Утилитарные команды / Utility Commands

### 1. Получение метаданных объекта / Get Object Metadata

**Команда:** `obj_meta`

**URL:**
```http
GET /{db}/obj_meta/{id}
```

**Ответ:**
```json
{
  "id": "123",
  "up": "1",
  "type": "18",
  "val": "ObjectName",
  "reqs": {
    "1": {
      "id": "456",
      "val": "AttributeName",
      "type": "3",
      "arr_id": null,
      "ref": null,
      "attrs": ""
    }
  }
}
```

---

### 2. Получение всех типов / Get All Types (Metadata)

**Команда:** `metadata`

**URL:**
```http
GET /{db}/metadata        # Все типы
GET /{db}/metadata/{id}   # Конкретный тип
```

**Ответ (массив):**
```json
[
  {
    "id": "18",
    "up": "0",
    "type": "3",
    "val": "User",
    "unique": "1",
    "reqs": [
      {
        "num": 1,
        "id": "41",
        "val": "Email",
        "orig": "3",
        "type": "3"
      }
    ]
  }
]
```

---

### 3. Получение списка типов / Get Terms List

**Команда:** `terms`

**URL:**
```http
GET /{db}/terms
```

**Ответ:**
```json
[
  {"id": 18, "type": 3, "name": "User"},
  {"id": 22, "type": 3, "name": "Report"}
]
```

---

### 4. Получение значений справочника / Get Reference Values

**Команда:** `_ref_reqs`

**URL:**
```http
GET /{db}/_ref_reqs/{ref_attribute_id}?q={search}&r={restrict_id}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `q` | string | Поисковый запрос |
| `r` | int | Ограничение по ID родителя |

**Ответ:**
```json
{
  "1": "Value 1",
  "2": "Value 2 / Extra Info",
  "3": "Value 3 / More Info"
}
```

---

### 5. Выход из системы / Logout

**Команда:** `exit`

**URL:**
```http
GET /{db}/exit
```

Удаляет токен пользователя и перенаправляет на страницу входа.

---

### 6. Создание новой базы данных / Create New Database

**Команда:** `_new_db`

**URL:**
```http
POST /my/_new_db?db={db_name}&template={template_name}
```

**Параметры:**
| Параметр | Тип | Описание |
|----------|-----|----------|
| `db` | string | Имя новой базы (3-15 символов, начинается с буквы) |
| `template` | string | Шаблон (ru, en) |
| `descr` | string | Описание базы |

**Ограничения:**
- Только из личного кабинета (`my`)
- Максимум 3 базы на бесплатном тарифе
- Имя не должно быть зарезервированным словом MySQL

---

### 7. Вызов внешнего коннектора / Call External Connector

**Команда:** `_connect`

**URL:**
```http
GET /{db}/_connect/{object_id}?params...
```

Вызывает внешний URL, сохраненный в атрибуте CONNECT объекта, передавая все GET-параметры.

---

## Коды ошибок / Error Codes

### HTTP коды

| Код | Описание |
|-----|----------|
| 200 | Успех |
| 400 | Неверный запрос |
| 401 | Не авторизован |
| 403 | Доступ запрещен |
| 404 | Объект/база не найден |

### Формат ошибки (JSON API)

```json
[{"error": "Error message"}]
```

### Типичные сообщения

| Сообщение | Описание |
|-----------|----------|
| `No authorization token provided` | Не передан токен авторизации |
| `Invalid database` | Неверное имя базы данных |
| `dBNotExists` | База данных не существует |
| `InvalidToken` | Неверный токен авторизации |
| `Object not found` | Объект не найден |
| `Cannot delete metadata` | Нельзя удалить метаданные |
| `You don't have permission` | Нет прав доступа |

---

## Рекомендуемые названия / Recommended Naming

Таблица соответствия старых и рекомендуемых названий команд:

### DML команды

| Старое имя | Рекомендуемое имя | HTTP метод | Описание |
|------------|-------------------|------------|----------|
| `_m_new` | `objects` | POST | Создание объекта |
| `_m_save` | `objects/{id}` | PUT | Сохранение объекта |
| `_m_set` | `objects/{id}/attributes` | PATCH | Установка атрибута |
| `_m_del` | `objects/{id}` | DELETE | Удаление объекта |
| `_m_move` | `objects/{id}/move` | POST | Перемещение объекта |
| `_m_up` | `objects/{id}/moveup` | POST | Перемещение вверх |
| `_m_ord` | `objects/{id}/order` | PATCH | Установка порядка |
| `_m_id` | `objects/{id}/id` | PATCH | Изменение ID |

### DDL команды

| Старое имя | Альтернативное | Рекомендуемое | HTTP метод | Описание |
|------------|----------------|---------------|------------|----------|
| `_d_new` | `_terms` | `types` | POST | Создание типа |
| `_d_save` | `_patchterm` | `types/{id}` | PUT | Сохранение типа |
| `_d_del` | `_deleteterm` | `types/{id}` | DELETE | Удаление типа |
| `_d_req` | `_attributes` | `types/{id}/attributes` | POST | Добавление реквизита |
| `_d_del_req` | `_deletereq` | `types/attributes/{id}` | DELETE | Удаление реквизита |
| `_d_ref` | `_references` | `types/{id}/references` | POST | Создание ссылочного типа |
| `_d_alias` | `_setalias` | `attributes/{id}/alias` | PATCH | Установка псевдонима |
| `_d_null` | `_setnull` | `attributes/{id}/required` | PATCH | Переключение NOT NULL |
| `_d_multi` | `_setmulti` | `attributes/{id}/multiselect` | PATCH | Переключение мульти-выбора |
| `_d_attrs` | `_modifiers` | `attributes/{id}/modifiers` | PUT | Установка модификаторов |
| `_d_up` | `_moveup` | `attributes/{id}/moveup` | POST | Перемещение вверх |
| `_d_ord` | `_setorder` | `attributes/{id}/order` | PATCH | Установка порядка |

### Утилитарные команды

| Текущее имя | Рекомендуемое | HTTP метод | Описание |
|-------------|---------------|------------|----------|
| `xsrf` | `auth/token` | GET | Получение токенов |
| `exit` | `auth/logout` | POST | Выход |
| `terms` | `types/list` | GET | Список типов |
| `metadata` | `types/metadata` | GET | Метаданные типов |
| `obj_meta` | `objects/{id}/meta` | GET | Метаданные объекта |
| `_ref_reqs` | `references/{id}/values` | GET | Значения справочника |
| `_new_db` | `databases` | POST | Создание базы |
| `_connect` | `objects/{id}/connect` | GET | Вызов коннектора |

---

## Примеры использования / Usage Examples

### Аутентификация и получение токена

```bash
# Получение XSRF токена
curl -X GET "https://example.com/mydb/xsrf"

# Авторизация через Basic Auth
curl -X GET "https://example.com/mydb/terms" \
  -H "Authorization: Basic $(echo -n 'user:password' | base64)"

# Авторизация через Bearer токен
curl -X GET "https://example.com/mydb/terms" \
  -H "Authorization: Bearer your_token_here"
```

### Работа с объектами

```bash
# Создание нового пользователя
curl -X POST "https://example.com/mydb/_m_new/18?up=1" \
  -H "Authorization: Bearer your_token" \
  -d "t18=newuser@example.com" \
  -d "t20=password123"

# Обновление объекта
curl -X POST "https://example.com/mydb/_m_save/123" \
  -H "Authorization: Bearer your_token" \
  -d "t18=updated@example.com"

# Установка одного атрибута
curl -X POST "https://example.com/mydb/_m_set/123" \
  -H "Authorization: Bearer your_token" \
  -d "t41=newemail@example.com"

# Удаление объекта
curl -X POST "https://example.com/mydb/_m_del/123" \
  -H "Authorization: Bearer your_token"
```

### Работа с типами (метаданными)

```bash
# Получение списка типов
curl -X GET "https://example.com/mydb/terms" \
  -H "Authorization: Bearer your_token"

# Создание нового типа
curl -X POST "https://example.com/mydb/_d_new" \
  -H "Authorization: Bearer your_token" \
  -d "val=NewType" \
  -d "t=3" \
  -d "unique=1"

# Добавление реквизита к типу
curl -X POST "https://example.com/mydb/_d_req/456?t=41" \
  -H "Authorization: Bearer your_token"
```

---

## Заключение / Conclusion

API предоставляет полный набор операций для работы с данными и метаданными в CRM-подобной структуре. Команды имеют как устаревшие названия (`_m_*`, `_d_*`), так и альтернативные более читаемые версии.

Для новых разработок рекомендуется использовать альтернативные названия команд и RESTful подход к именованию ресурсов.

---

**Версия:** 1.0
**Дата:** 2026-02-23
**Автор:** AI Issue Solver
