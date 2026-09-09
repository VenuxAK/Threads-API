# Architecture

> ThreadsApp API — Laravel 12 + Nuwave Lighthouse (GraphQL) + Hybrid MySQL/MongoDB + RoadRunner

## 1. Overview

ThreadsApp is a high-performance, full-stack social media application modeled after Meta's Instagram Threads. The backend is built to balance massive write workloads with strong relational guarantees:

- **Write-heavy feeds**: Stored in **MongoDB** for schema flexibility, horizontal scalability, and deep hierarchical comment graph lookups (`$graphLookup`).
- **Relational integrity**: Stored in **MySQL** for transactional safety, identity management, and atomic counter caches (`post_meta_data`, `post_likes`, `post_reposts`).
- **GraphQL Protocol**: Single, unified `POST /graphql` endpoint powered by **Nuwave Lighthouse**, eliminating over-fetching and under-fetching.
- **Low latency**: **RoadRunner** persistent worker processes via Laravel Octane, avoiding framework bootstrap overhead on each request.
- **Security-first**: Integrated Web Application Firewall (WAF) and strict GraphQL query depth/complexity limiters.

```mermaid
flowchart LR
    Client[Nuxt 4 SPA] --> RR[RoadRunner<br/>persistent workers]
    RR --> MW[Middleware Stack<br/>Sanctum Stateful<br/>WAF Rate Limiting<br/>SecurityHeaders]
    MW --> GQL[Nuwave Lighthouse<br/>GraphQL Engine]
    GQL --> RES[GraphQL Resolvers<br/>App/GraphQL/Queries<br/>App/GraphQL/Mutations]
    RES --> SVC[Services & Actions<br/>PostService / UserService<br/>LikePost / RepostPost]
    SVC --> DBM[(MongoDB 6<br/>posts, comments)]
    SVC --> DBS[(MySQL 8<br/>users, metadata, likes)]
    RES --> BL[Batch Loaders<br/>PostTransformer<br/>Cache 5m]
    BL --> JSON[JSON Response<br/>data & errors]
    JSON --> Client
```

```mermaid
flowchart TB
    subgraph Client Layer
        SPA[Nuxt 4 SPA Client]
        GIQL[GraphiQL IDE Explorer]
    end
    subgraph Server Runtime
        RR2[RoadRunner Persistent Workers]
        MW2[WAF & Security Headers]
        AUTH_RT[Auth Routes /auth/*]
        GQL_RT[GraphQL Endpoint /graphql]
    end
    subgraph Hybrid Storage Layer
        MySQL[(MySQL 8<br/>Users, Tokens, Likes, Metadata)]
        Mongo[(MongoDB 6<br/>Posts, Comments, Hashtags)]
        Cache[(Redis / Array Cache)]
    end
    SPA --> RR2
    GIQL --> RR2
    RR2 --> MW2
    MW2 --> AUTH_RT
    MW2 --> GQL_RT
    AUTH_RT --> MySQL
    GQL_RT --> MySQL
    GQL_RT --> Mongo
    GQL_RT --> Cache
```

---

## 2. Technology Stack

| Layer | Choice | Reason |
| :--- | :--- | :--- |
| **Framework** | Laravel 12 (PHP 8.3) | Mature ecosystem, Sanctum authentication, robust dependency injection. |
| **Application Server** | RoadRunner (`spiral/roadrunner-http`) + Octane | High-concurrency persistent workers (~3x throughput vs standard PHP-FPM). |
| **GraphQL Engine** | Nuwave Lighthouse (`^6.70`) | Directive-driven schema (`@guard`), AST caching, depth/complexity security limits. |
| **Developer Tooling** | Laravel GraphiQL (`mll-lab/laravel-graphiql`) | Interactive schema explorer and documentation viewer at `/graphiql`. |
| **Relational DB** | MySQL 8 | Foreign keys, transactional integrity, and atomic counter updates. |
| **Document DB** | MongoDB 6 via `mongodb/laravel-mongodb` | Scalable post feed storage and recursive comment graph traversal. |
| **Security** | Custom WAF + SecurityHeaders | Rate limiting, SQLi/XSS pattern defense, HSTS, CSP headers. |

---

## 3. Project Structure

```
app/
  Actions/              # Atomic write operations (LikePostAction, RepostPostAction, CreateCommentAction)
  Console/Commands/     # WafManageCommand, CreateSearchIndexes
  DTOs/                 # Typed parameter containers (CommentData, InteractionResult)
  GraphQL/
    Mutations/          # GraphQL mutation resolvers (PostMutation, InteractionMutation, CommentMutation, ProfileMutation)
    Queries/            # GraphQL query resolvers (FeedQuery, PostQuery, UserQuery, CommentThreadQuery, etc.)
  Http/
    Controllers/Auth/   # Session auth endpoints (Login, Register, Logout, Password Reset)
    Middleware/         # WebApplicationFirewall, SecurityHeaders
  Models/               # User (MySQL), Post/Comment (MongoDB), PostMetaData/Like/Repost (MySQL)
  Policies/             # PostPolicy, CommentPolicy
  Services/             # PostService, CommentService, UserService, SearchService
  Transformers/         # PostTransformer, CommentTransformer (batch hydration & cache)
config/
  lighthouse.php        # GraphQL guards, schema cache, and depth/complexity security limits
  waf.php               # WAF patterns, IP blacklists, and endpoint rate limits
graphql/
  schema.graphql        # Authoritative GraphQL schema definition
routes/
  auth.php              # Session authentication routes (/auth/*)
  web.php               # Web routing bootstrap
docs/
  API_CONTRACT.md       # GraphQL operations and authentication specification
  ARCHITECTURE.md       # System architecture and data flow (this file)
  GRAPHQL.md            # GraphQL developer guide and learning handbook
  SSD.md                # System sequence diagrams
  WAF_*.md              # WAF implementation and configuration
```

---

## 4. Request Lifecycle

```mermaid
sequenceDiagram
    autonumber
    participant C as Nuxt 4 Client
    participant RR as RoadRunner Worker
    participant MW as WAF & Security Headers
    participant GQL as Lighthouse GraphQL Engine
    participant RES as Query / Mutation Resolver
    participant DB as MySQL & MongoDB
    participant TF as PostTransformer Batcher

    C->>RR: POST /graphql (with session cookie)
    RR->>MW: Pass to middleware
    MW->>MW: Check rate limits & pattern scans
    MW->>GQL: Dispatch to GraphQL router
    GQL->>GQL: Validate AST depth (max 8) & complexity (max 200)
    GQL->>GQL: Check @guard session authentication
    GQL->>RES: Invoke Resolver (e.g. FeedQuery)
    RES->>DB: Fetch posts from MongoDB
    RES->>TF: Batch load authors & metadata from MySQL
    TF->>DB: User::whereIn(...) & PostMetaData::whereIn(...)
    TF-->>RES: Hydrated post records
    RES-->>GQL: Resolved GraphQL types
    GQL-->>C: JSON { data: { feed: { ... } } }
```

---

## 5. The Hybrid Storage & Batch Loading Pattern

Because `Post` lives in MongoDB and `User` lives in MySQL, cross-database joins are impossible at the database engine level.

### The Batch Loader Solution:
Instead of running a separate query for each post's author ($N+1$ queries):
1. **Extraction**: Collect all unique `user_id`s from the MongoDB post collection.
2. **Bulk Fetch**: Execute a single `User::whereIn('id', $userIds)` query against MySQL.
3. **Caching**: Store the fetched authors in memory with a 5-minute TTL via `Cache::remember`.
4. **Metadata Bulk Fetch**: Execute a single `PostMetaData::whereIn('post_id', $postIds)` query.
5. **Assembly**: PostTransformer maps the MySQL authors and metadata onto the MongoDB post objects before serializing them into the GraphQL response.
