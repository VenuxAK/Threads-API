# Backend (API) - Agent Guidelines & Architecture

## 1. Stack & Runtime
- **Language & Framework**: PHP 8.3 / Laravel 12.
- **Application Server**: RoadRunner via Laravel Octane (`laravel/octane`).
- **GraphQL Engine**: Nuwave Lighthouse (`nuwave/lighthouse`) with GraphQL playground/GraphiQL (`mll-lab/laravel-graphiql`).
- **Databases**:
  - **MySQL 8**: `users`, `post_meta_data`, `post_likes`, `post_reposts`, `personal_access_tokens`, `cache`, `jobs`.
  - **MongoDB 6** (`mongodb/laravel-mongodb`): `posts`, `comments`, `tags`.
- **Security**: Custom Web Application Firewall (`WebApplicationFirewall`), SecurityHeaders middleware, and Lighthouse GraphQL security directives.

---

## 2. RoadRunner & Octane Lifecycle Rules
Because RoadRunner maintains persistent worker processes in memory between requests:
- **No Static Leaks**: Never store request-specific data or user instances in static properties or singleton services without explicitly resetting them on request termination.
- **Auth Guard Awareness**: Always access the authenticated user via `Auth::user()` or `$request->user()`, never cache the user object statically.
- **Octane Listeners**: When registering services that hold memory across cycles, ensure clean-up hooks are registered in `config/octane.php`.

---

## 3. Hybrid Database Architecture (MySQL + MongoDB)
This backend uses a hybrid storage model to maximize write scalability while retaining relational integrity:

```
┌─────────────────────────────────┐       ┌─────────────────────────────────┐
│        MongoDB (NoSQL)          │       │          MySQL (SQL)            │
│  - posts (content, tags)        │       │  - users (auth, profile)        │
│  - comments (hierarchical tree) │       │  - post_meta_data (counts)      │
│                                 │       │  - post_likes (user_id, post_id)│
│                                 │       │  - post_reposts (user_id, post) │
└────────────────┬────────────────┘       └────────────────┬────────────────┘
                 │                                         │
                 └───────────────┐         ┌───────────────┘
                                 ▼         ▼
                      GraphQL DataLoader Layer
                  (Batches IDs to resolve without N+1)
```

### Critical Rules:
1. **No Cross-DB Joins in Eloquent**: `Post` is an instance of `MongoDB\Laravel\Eloquent\Model`, while `User` is `Illuminate\Database\Eloquent\Model`. Eager loading across connections (`Post::with('author')`) must not be assumed to work natively.
2. **Mandatory Batch Loading (DataLoaders)**:
   - When resolving relational fields for a list of posts or comments (e.g. `Post.author`, `Post.interactions`, `Post.is_liked`), always collect all target IDs and perform a single batch query (e.g., `User::whereIn('id', $ids)->get()`).
   - Use `Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry` or custom memoized batch resolvers.
3. **Write Integrity & Manual Rollback**:
   - Because cross-database distributed transactions are unavailable, multi-database writes must implement manual compensation.
   - Example: In `PostService::createPost`, create the MongoDB post first, then insert `PostMetaData` in MySQL. If MySQL fails, explicitly delete the MongoDB post before throwing an exception.

---

## 4. GraphQL & Lighthouse Standards
- **Schema Location**: `graphql/schema.graphql` (and modular imports if expanded).
- **Guards**: Always annotate authenticated queries and mutations with `@guard` (using Sanctum).
- **Paginators**: Always use standardized pagination matching the `PostPaginator` structure (`data: [Post!]!`, `pagination: PaginationInfo!`).
- **Security Rules**:
  - `security.max_query_depth` is strictly enforced at **8** to prevent malicious deeply nested queries.
  - `security.max_query_complexity` is set to **200**.
  - Introspection must be disabled in production environments (`LIGHTHOUSE_SECURITY_DISABLE_INTROSPECTION=true`).

---

## 5. Commenting & Code Quality Standards
- **Medium-Length Comments**: Every new or refactored class, resolver method, service action, and migration must include a 2 to 4-line docblock or inline comment explaining:
  1. The responsibility of the component.
  2. Any cross-database dependencies (e.g., why batching is used).
- **Pint Formatting**: Always run `vendor/bin/pint` to maintain PSR-12 code styling standards before committing.
- **Testing**:
  - Keep feature tests in `tests/Feature/`.
  - Schema can be verified at any time using `php artisan lighthouse:validate-schema`.
