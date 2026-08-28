# Software Design Document (SSD)

> ThreadsApp API — Version 1.0 — 2026-08-25

## 1. Purpose & Scope

This document captures requirements, architecture, data design, component design, and operational concerns for the ThreadsApp API. It complements `ARCHITECTURE.md` (how it’s built) and `API_CONTRACT.md` (how to call it) with the *why* and *what*.

### 1.1 Stakeholders

* End users (mobile/web clients via `/api/v1/*`)
* Platform team (operate MySQL/Mongo/Redis/RoadRunner)
* Security (WAF ownership)

### 1.2 Definitions

* **SSD** — Software Design Document.
* **WAF** — Web Application Firewall (middleware).
* **Hybrid DB** — MySQL for relational data, MongoDB for documents.

## 2. Requirements

### 2.1 Functional

| ID | Requirement | Priority | Status |
|----|-------------|----------|--------|
| F-01 | Register / login / logout / password reset / email verification | Must | Done (Breeze `routes/auth.php`) |
| F-02 | Create / read / update / delete own posts with hashtag extraction | Must | Done (`PostService`+`PostPolicy`) |
| F-03 | Public feed + per-user post/repost listings, paginated | Must | Done (`PostService::getFeed`, `UserService`) |
| F-04 | Like / unlike, repost, share, interaction counters | Must | Done (`InteractionService`+Actions) |
| F-05 | Comment on post, reply to comment, fetch replies/thread, delete own comment | Must | Done (`CommentService` → `CreateCommentAction`, `$graphLookup`) |
| F-06 | Search users; optional post search with `?posts=include` | Should | Done (`SearchService`) |
| F-07 | Utility probes `waf-test`, `ping-mongodb` (unauthenticated, sanitized) | Should | Done (`UtilityController`) |
| F-08 | Security headers + WAF on all API routes | Must | Done (`SecurityHeaders`, `WebApplicationFirewall`) |

### 2.2 Non-Functional

| ID | Requirement | Target |
|----|-------------|--------|
| NF-01 | Availability | 99.9% (RoadRunner workers + stateless API) |
| NF-02 | Latency p95 | <150 ms for feed/search (cached user batches, indexed Mongo) |
| NF-03 | Scale | 10k rps feed reads; Mongo horizontal shard-ready |
| NF-04 | Security | OWASP Top-10 mitigations via WAF + headers + validation + hash_equals bypass |
| NF-05 | Operability | `php artisan waf …` + `CreateSearchIndexes` + health `/up` + `/api/ping-mongodb` + daily logs |
| NF-06 | Testability | `RefreshDatabase`, `Sanctum::actingAs`, PHPUnit 11; `WafTest` as contract |

### 2.3 Out of Scope (v1)

Real-time (WebSockets), media uploads beyond `file_upload` validation, moderation queue, notifications, OAuth providers.

## 3. System Overview

```mermaid
flowchart TB
    C[Clients<br/>web / mobile] --> HTTPS
    HTTPS --> RR[RoadRunner<br/>persistent workers]
    RR --> LARAVEL[Laravel 12<br/>bootstrap/app.php]
    LARAVEL --> MW[api middleware<br/>Sanctum Stateful<br/>WAF<br/>SecurityHeaders]
    MW --> ROUTER[Router<br/>api.php / web.php→auth.php]
    ROUTER --> CTRL[Controllers → Services → Actions / DTOs]
    CTRL --> MySQL[(MySQL 8<br/>users, post_meta_data<br/>likes, reposts)]
    CTRL --> Mongo[(MongoDB 6<br/>posts, comments, tags)]
    CTRL --> Redis[(Redis / array cache<br/>rate limit, user batch)]
    MySQL -. logical post_id .-> Mongo
```

## 4. Architecture Decisions (ADRs)

| ADR | Decision | Rationale | Consequence |
|-----|----------|-----------|-------------|
| 1 | Hybrid MySQL+Mongo | Feed benefits from document flexibility + scale; counters need relational integrity | Cross-DB compensation instead of XA |
| 2 | RoadRunner over php-fpm | Persistent bootstrap, lower latency; no Swoole extension | Binary `rr` shipped, config in `.rr.yaml` |
| 3 | Regex WAF in middleware | No extra proxy, simple `config/waf.php`, `monitor` vs `protect` | Regex brittle; mitigated by tests + allowlist |
| 4 | Services+Actions+Transformers | Thin controllers, testable orchestration, N+1 batch loading | More indirection, but matches existing refactor |
| 5 | `hash_equals` for bypass | Timing-safe compare for `X-WAF-Bypass` | Requires `is_string` guard |
| 6 | `$graphLookup` for threads | Single aggregation for nested replies vs N+1 frontier loop | Ties to Mongo; fallback to iterative loop if needed |

## 5. Data Design

### 5.1 ERD (logical) — see `database/SCHEMA.md` for full DDL

```mermaid
erDiagram
    users ||--o{ post_meta_data : owns
    users ||--o{ post_likes : likes
    users ||--o{ post_reposts : reposts
    post_meta_data ||--|| posts : logical
    posts ||--o{ comments : has
    comments ||--o{ comments : replies
    users ||--o{ posts : authors
    users ||--o{ comments : authors

    users {
        bigint id PK
        string username UK
        string email UK
        string password
        datetime email_verified_at
    }
    post_meta_data {
        bigint id PK
        varchar post_id FK
        bigint user_id FK
        int likes_count
        int comments_count
        int reposts_count
    }
    post_likes {
        bigint id PK
        varchar post_id
        bigint user_id
    }
    post_reposts {
        bigint id PK
        varchar post_id
        bigint user_id
    }
    posts {
        ObjectId _id PK
        string content
        string tags
        string user_id
    }
    comments {
        ObjectId _id PK
        string post_id
        string user_id
        string parent_id
    }
```

> Full columns, indexes and MySQL ↔ Mongo logical links: `database/SCHEMA.md` (`docs/database/SCHEMA.md` mirror).

### 5.2 MySQL Schema (key tables)

```sql
users(id PK, name, username UNIQUE, email UNIQUE, avatar, bio, email_verified_at, password hashed, timestamps)
post_meta_data(id PK, post_id VARCHAR(255), user_id FK, likes_count INT DEFAULT 0, comments_count INT DEFAULT 0, shares_count INT DEFAULT 0, reposts_count INT DEFAULT 0)
post_likes(id PK, post_id VARCHAR, user_id FK, timestamps, UNIQUE(post_id,user_id))
post_reposts(id PK, post_id VARCHAR, user_id FK, timestamps, UNIQUE(post_id,user_id))
```

### 5.3 Mongo Schema

```js
posts: { _id:ObjectId, content:String, tags:String[], user_id:Number, created_at:ISODate, updated_at:ISODate }
comments: { _id:ObjectId, content:String, post_id:String, user_id:Number, parent_id:String|null, created_at:ISODate }
```

Indexes: `posts.user_id`, `posts.tags`, `posts.created_at` (via `CreateSearchIndexes`); `comments.post_id`, `comments.parent_id`.

### 5.4 Counters

Denormalized in `post_meta_data` for fast feed hydration. Maintained by Actions; `CommentService::deleteComment` does `where(comments_count,>,0)->decrement`; failures are logged `warning` (tolerable drift).

## 6. Component Design

### 6.1 Controllers

* `PostController` (7 actions) — `PostRequest` validation, `PostPolicy`, `PostService`, `PostTransformer`.
* `CommentController` — delegates entirely to `CommentService` (thin, see `ARCHITECTURE.md:6`).
* `PostInteractionController` — like/share/repost + `check*` + `interactions`.
* `ProfileController` → `UserService`.
* `SearchController` → `SearchService`.
* `UtilityController` — `wafTest` (diagnostics only) + `pingMongoDb` (`config()` + `DB::connection('mongodb')`, generic 500).
* `Auth/*` — Breeze session flow.

### 6.2 Services

* `PostService::createPost` — hashtag filter → `Post::create` → `PostMetaData::create` or rollback; `deletePost` compensates metadata.
* `CommentService::getThread` — `$graphLookup` from root comment → flatten/sort asc → attach `replying_to`.
* `SearchService` — sanitize query, `User LIKE`, optional `Post content/tags regex`.
* `UserService` — `getAuthUser`, `getUserWithPosts`, `getReposts` via `PostService::getRepostedPosts`.

### 6.3 Middleware

* `WebApplicationFirewall` — ordered: enabled? → bypass? → IP → rate limit → patterns → sizes → uploads. `hash_equals` for bypass; `RateLimiter` with `waf:*` keys.
* `SecurityHeaders` — `BinaryFileResponse` bypass, HSTS, CSP prod-only, `Cache-Control` for `api/*`.
* `EnsureEmailIsVerified` — alias `verified`.

### 6.4 Transformers

`PostTransformer::transformPosts` handles `Collection` vs `LengthAwarePaginator`, caches user batches (`md5(userIds)` 300s), fetches metadata/likes/reposts in 3 queries, maps to feed shape.

```mermaid
classDiagram
    class User {
        +id: bigint
        +username: string
        +email: string
        +password: hashed
        +avatar: string
        +bio: string
    }
    class Post {
        +_id: ObjectId
        +content: string
        +tags: string[]
        +user_id: int
    }
    class Comment {
        +_id: ObjectId
        +post_id: string
        +user_id: int
        +parent_id: string
        +content: string
    }
    class PostMetaData {
        +post_id: string
        +user_id: int
        +likes_count: int
        +comments_count: int
        +reposts_count: int
    }
    class PostLike {
        +post_id: string
        +user_id: int
    }
    class PostRepost {
        +post_id: string
        +user_id: int
    }
    User "1" --o "0..*" Post : authors
    User "1" --o "0..*" Comment : authors
    Post "1" -- "1" PostMetaData : logical
    Post "1" --o "0..*" Comment : has
    Comment "1" --o "0..*" Comment : replies
    User "1" --o "0..*" PostLike : likes
    User "1" --o "0..*" PostRepost : reposts

    class PostService {
        +getFeed() Paginator
        +createPost() Post
        +deletePost() bool
    }
    class CommentService {
        +getComments()
        +createComment()
        +getThread() graphLookup
    }
    class InteractionService {
        +like() / unlike()
        +repost()
    }
    PostService ..> Post : creates
    CommentService ..> Comment : creates
    InteractionService ..> PostLike
    InteractionService ..> PostRepost
```

## 7. Sequence Diagrams

### 7.1 Create Post

```mermaid
sequenceDiagram
    participant C as Client
    participant WAF as WAF
    participant PC as PostController
    participant PS as PostService
    participant MO as Mongo posts
    participant MY as MySQL post_meta_data
    participant TF as PostTransformer
    C->>WAF: POST /api/v1/me/posts {content} Bearer
    WAF->>PC: 200 pass (monitor/protect)
    PC->>PC: validate PostRequest max:5000 + Policy
    PC->>PS: createPost(content, userId)
    PS->>PS: filterHashTags()
    PS->>MO: Post::create{content,tags,user_id}
    MO-->>PS: _id
    PS->>MY: PostMetaData::create{post_id:_id, user_id}
    alt MySQL fail
        PS->>MO: delete(_id) compensate
        PS-->>PC: throw → 500
        PC-->>C: {success:false, code:500}
    else success
        PS-->>PC: Post
        PC->>TF: transformPosts([post]) batch users+metadata
        TF-->>PC: shaped post
        PC-->>C: 201 {success:true, data:{post}}
    end
```

### 7.2 Comment Thread

```mermaid
sequenceDiagram
    participant C as Client
    participant CC as CommentController
    participant CS as CommentService
    participant MO as Mongo comments
    participant MY as MySQL users
    C->>CC: GET /api/v1/comments/{id}/thread Bearer
    CC->>CS: getThread(id)
    CS->>MO: aggregate $graphLookup descendants maxDepth 50
    MO-->>CS: [descendants] sorted asc
    CS->>MY: User::whereIn(user_ids)
    MY-->>CS: users keyed
    CS->>CS: map replying_to + author
    CS-->>CC: thread[]
    CC-->>C: 200 {success:true, data:{thread}}
```

### 7.3 Like / Repost Toggle

```mermaid
sequenceDiagram
    participant C as Client
    participant IC as PostInteractionController
    participant IS as InteractionService
    participant MY as MySQL likes/reposts
    participant MD as MySQL post_meta_data
    C->>IC: POST /api/v1/posts/{id}/like
    IC->>IS: like(postId, userId)
    IS->>MY: PostLike::firstOrCreate UNIQUE
    alt created
        IS->>MD: increment likes_count
    else exists
        IS-->>IC: already liked (idempotent)
    end
    IS-->>IC: {liked:true, likes_count}
    IC-->>C: 200 {data:{liked, likes_count}}
```

### 7.4 Search

```mermaid
sequenceDiagram
    participant C as Client
    participant SC as SearchController
    participant SS as SearchService
    participant MY as MySQL users
    participant MO as Mongo posts
    C->>SC: POST /api/v1/search?posts=include {query, page}
    SC->>SS: search(query, includePosts, page)
    SS->>SS: sanitize preg_replace
    SS->>MY: User where username/name LIKE %q% limit 10
    MY-->>SS: users
    alt includePosts
        SS->>MO: Post where content regex /q/i or tags regex
        MO-->>SS: posts paginated
        SS->>SS: PostTransformer batch
    end
    SS-->>SC: {users, posts?, search_metadata}
    SC-->>C: 200 {success:true, data:{...}}
```

### 7.5 WAF Decision

```mermaid
flowchart TB
    REQ[Request] --> EN{enabled?}
    EN -- no --> PASS[→ next]
    EN -- yes --> BYP{hash_equals<br/>X-WAF-Bypass?}
    BYP -- yes --> PASS
    BYP -- no --> IP{IP black/white?}
    IP -- block --> BLK[403]
    IP -- pass --> RL{RateLimiter}
    RL -- 429 --> RLBLK[429 retry_after]
    RL -- pass --> PAT{SQLi / XSS / traversal / UA<br/>+ size + upload}
    PAT -- violation & monitor --> LOG[log only] --> PASS
    PAT -- violation & protect --> BLK2[403 reason?]
    PAT -- clean --> PASS
```

## 8. Security Design

* **WAF** — patterns (`config/waf.php`): SQLi (union/select/insert/drop/OR 1=1/--/benchmark…), XSS (`<script`/`javascript:`/on*/`alert`/`document.`), traversal (`../`/`/etc/passwd`/`.env`), UA block (`sqlmap`/`nikto`/…); file block list (`php`/`exe`/…); limits (`max_post_size` 10 MB, `max_uri_length` 2 KB). Mode `monitor`/`protect`, `bypass_tokens` via `X-WAF-Bypass`, IP lists.
* **Headers** — as in §6.3.
* **Auth** — Sanctum stateful (`EnsureFrontendRequestsAreStateful` before WAF), `HasApiTokens`, `password:hashed`, `remember_token` hidden, `signed` verify, `throttle:6,1`.
* **Sanitization** — `UtilityController` no reflection, `SearchService` strips non-alnum, WAF scans `headers+body+uri`.
* **Logging** — `waf` channel (`daily`, `warning`, 30 days), `Log::channel($channel)->$level` with `{type,ip,method,uri,ua,details}`.

## 9. Performance & Scalability

* RoadRunner reuse, OpCache, `Cache::remember` for users, Mongo `latest()` with indexes, pagination caps, `RateLimiter` in Redis/array.
* Horizontal: stateless API (except session for `/auth`), Mongo shard by `user_id` or `_id`, MySQL read replica for feed metadata.

## 10. Operational

* Env: `WAF_*`, `MONGODB_URI/DATABASE`, `REDIS_*`, `APP_ENV` (see `README: Quick Start`).
* Commands: `php artisan waf status|logs|clear-rate-limits|clear-logs`, `php artisan search:indexes` (CreateSearchIndexes).
* Health: `GET /up`, `GET /api/ping-mongodb`.
* Logs: `storage/logs/waf.log` (also `laravel.log` via `daily` channel).
* CI: `php artisan test --filter=WafTest|MongoDBTest|…`, `vendor/bin/pint --dirty`.

## 11. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Counter drift (cross-DB) | Stale likes/comments | Periodic reconciliation job (future) |
| Regex false positives | Block legitimate posts | `monitor` mode + whitelist + pattern tuning |
| Mongo availability | Feed outage | `ping-mongodb` + retry + circuit breaker (future) |
| N+1 on transformers | Latency | Batch `whereIn` + cache; monitor `Cache` hit rate |

## 12. Future Work

* Media uploads (S3), notifications, WebSocket feed, moderation, OAuth, OpenAPI spec generation from `API_CONTRACT.md`.

## 13. References

* `ARCHITECTURE.md`, `API_CONTRACT.md`, `docs/WAF_README.md`, `docs/WAF_IMPLEMENTATION.md`.
* OWASP WAF / Top 10, Laravel 12 docs, MongoDB `$graphLookup`.

