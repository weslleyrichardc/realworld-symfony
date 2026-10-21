# ![RealWorld Example App](logo.png)

> ### Symfony codebase containing real world examples (CRUD, auth, advanced patterns, etc) that adheres to the [RealWorld](https://github.com/gothinkster/realworld) spec and API.

[![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4?logo=php)](https://www.php.net)
[![Doctrine](https://img.shields.io/badge/Doctrine-ORM-3-525252?logo=doctrine)](https://www.doctrine-project.org)

This codebase is a fully fledged API implementing the [RealWorld specification](https://github.com/gothinkster/realworld/blob/main/docs/src/content/docs/specifications/backend/endpoints.md):
authentication, CRUD operations, routing, pagination, filtering and more.

It is built with **Symfony 7.4**, **Doctrine ORM 3** and **PostgreSQL 16**, and is
compatible with the official [RealWorld API conformance tests](https://github.com/gothinkster/realworld/tree/main/specs/api).

## Features

- JWT authentication with [lexik/jwt-authentication-bundle](https://github.com/lexik/JWT-Authentication-Bundle)
- Users, profiles, follow relationships, articles, tags, comments and favorites
- Filtering of the article list by `tag`, `author` and `favorited`, with `limit` and `offset` pagination
- A feed of the articles written by the users the authenticated one follows
- Errors rendered with the envelope of the specification: `{"errors": {"<resource>": ["<message>"]}}`
- CORS enabled out of the box for browser front-ends
- A functional test suite covering every endpoint

## Requirements

| Dependency | Version |
| ---------- | ------- |
| PHP        | 8.2 or higher (8.4 recommended) |
| Composer   | 2.x |
| PostgreSQL | 16 or higher |

## Getting started

### 1. Install the dependencies

```bash
composer install
```

### 2. Configure the environment

The committed `.env` holds the defaults, including a database URL that points at a
local PostgreSQL instance. Override it in `.env.local`, which is never committed:

```dotenv
DATABASE_URL="postgresql://postgres:password@127.0.0.1:5432/realworld?serverVersion=16&charset=utf8"
JWT_PASSPHRASE="a-passphrase-of-your-own"
```

The JWT signing keys are generated once and are ignored by git:

```bash
php bin/console lexik:jwt:generate-keypair
```

### 3. Create the database and the schema

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

A `compose.yaml` file is provided to start PostgreSQL in Docker:

```bash
docker compose up -d
```

### 4. Run the application

```bash
symfony serve
```

The API is then available on [http://localhost:8000/api](http://localhost:8000/api).

## The API

Every endpoint lives below `/api` and answers with JSON. The authenticated
endpoints expect the JWT in the `Authorization` header, using the `Token`
scheme of the specification:

```http
Authorization: Token <jwt>
```

The `Bearer` scheme and a standalone `Token` header are accepted as well.

| Method   | Endpoint                              | Description                                    |
| -------- | ------------------------------------- | ---------------------------------------------- |
| `POST`   | `/api/users`                          | Register an account                            |
| `POST`   | `/api/users/login`                    | Exchange the credentials for a JWT              |
| `GET`    | `/api/user`                           | The authenticated account                      |
| `PUT`    | `/api/user`                           | Update the authenticated account                |
| `GET`    | `/api/profiles/{username}`            | The profile of a user                          |
| `POST`   | `/api/profiles/{username}/follow`     | Follow a user                                  |
| `DELETE` | `/api/profiles/{username}/follow`     | Stop following a user                          |
| `GET`    | `/api/articles`                       | The articles, optionally filtered              |
| `GET`    | `/api/articles/feed`                  | The articles of the users the viewer follows   |
| `POST`   | `/api/articles`                       | Publish an article                             |
| `GET`    | `/api/articles/{slug}`                | An article                                     |
| `PUT`    | `/api/articles/{slug}`                | Update an article                              |
| `DELETE` | `/api/articles/{slug}`                | Delete an article                              |
| `POST`   | `/api/articles/{slug}/favorite`       | Favorite an article                            |
| `DELETE` | `/api/articles/{slug}/favorite`       | Remove an article from the favorites           |
| `GET`    | `/api/articles/{slug}/comments`       | The comments of an article                     |
| `POST`   | `/api/articles/{slug}/comments`       | Comment an article                             |
| `DELETE` | `/api/articles/{slug}/comments/{id}`  | Delete a comment                               |
| `GET`    | `/api/tags`                           | The tags in use                                |

### Example

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/users \
  -H 'Content-Type: application/json' \
  -d '{"user":{"username":"jake","email":"jake@example.com","password":"password123"}}' \
  | jq -r .user.token)

curl -s -X POST http://localhost:8000/api/articles \
  -H "Authorization: Token $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"article":{"title":"How to train your dragon","description":"Ever wonder how?","body":"You have to believe","tagList":["dragons"]}}'
```

## Tests

The functional test suite runs against PostgreSQL and needs its own database,
which is created from the migrations:

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test
php bin/console phpunit
```

### Specification conformance

The implementation is checked against the official
[RealWorld API test suite](https://github.com/gothinkster/realworld/tree/main/specs/api),
which ships the endpoints as [Hurl](https://hurl.dev) files and as a
[Bruno](https://www.usebruno.com) collection. With the application served on
`http://localhost:8000`, run:

```bash
git clone https://github.com/gothinkster/realworld.git
HOST=http://localhost:8000 ./realworld/specs/api/hurl/run-hurl-tests.sh
```

## Project layout

```
config/            Configuration of the framework, the security and the bundles
migrations/        The migrations of the database schema
src/Controller/    The HTTP endpoints, one controller per resource
src/Entity/        The mapped entities
src/Doctrine/      The custom Doctrine types
src/EventSubscriber/  The rendering of the errors
src/Exception/     The exceptions carrying the error envelope
src/Http/          The reading and the validation of the request bodies
src/Presenter/     The rendering of the resources
src/Repository/    The queries of the entities
src/Security/      The JWT authenticator and the token extractor
src/Service/       The write side of the resources
tests/             The functional test suite
```

## License

This project is released under the [MIT license](LICENSE).
