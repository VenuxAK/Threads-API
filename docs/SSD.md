# Software Design Document (SSD)

> ThreadsApp API — Version 2.0 (GraphQL Architecture)

## 1. Purpose & Scope

This document captures requirements, architectural decisions, component interactions, and sequence workflows for the ThreadsApp API. It serves as the authoritative technical reference for developers maintaining and extending the platform.

### 1.1 Definitions
* **SSD** — Software Design Document.
* **WAF** — Web Application Firewall middleware.
* **Hybrid DB** — MySQL for relational integrity and counters, MongoDB for content documents.
* **Lighthouse** — Nuwave Lighthouse GraphQL server for Laravel.

---

## 2. Requirements Matrix

### 2.1 Functional Requirements

| ID | Requirement | Priority | Implementation |
| :--- | :--- | :--- | :--- |
| **F-01** | User registration, login, logout, password reset | Must | Laravel Breeze HTTP routes (`/auth/*`) |
| **F-02** | Create, read, update, delete own posts | Must | GraphQL `createPost`, `updatePost`, `deletePost` mutations |
| **F-03** | Paginated global feed and user post feeds | Must | GraphQL `feed`, `myPosts`, `userPosts` queries with batch loading |
| **F-04** | Like, unlike, and repost posts with counters | Must | GraphQL `likePost`, `unlikePost`, `repost` mutations |
| **F-05** | Liked posts feed | Must | GraphQL `myLikedPosts` query |
| **F-06** | Root comments and deep nested comment threads | Must | GraphQL `postComments`, `commentThread` (via `$graphLookup`), `createComment` |
| **F-07** | Search users and posts with hashtag matching | Should | GraphQL `search` query with MongoDB regex matching |
| **F-08** | WAF protection and GraphQL depth/complexity limits | Must | WAF middleware + Lighthouse security configurations |

### 2.2 Non-Functional Requirements

| ID | Requirement | Target Metric |
| :--- | :--- | :--- |
| **NF-01** | **Throughput** | 10k+ requests/sec using RoadRunner persistent PHP workers via Laravel Octane. |
| **NF-02** | **Latency** | p95 < 100ms for feeds via bulk MySQL author batch hydration and 5-minute caching. |
| **NF-03** | **Security** | OWASP Top-10 protection via WAF, max query depth of 8, and max complexity of 200. |
| **NF-04** | **Consistency** | Zero cross-database N+1 queries using centralized DataLoader resolvers. |

---

## 3. System Sequence Diagrams (SSD)

### 3.1 Authentication & Session Bootstrap

```mermaid
sequenceDiagram
    autonumber
    participant Client as Nuxt 4 Client
    participant WAF as WAF Middleware
    participant Auth as AuthenticatedSessionController
    participant MySQL as MySQL Users Table

    Client->>WAF: GET /sanctum/csrf-cookie
    WAF-->>Client: 204 No Content + Set-Cookie (XSRF-TOKEN)

    Client->>WAF: POST /auth/login {email, password}
    WAF->>Auth: Pass sanitized request
    Auth->>MySQL: Verify hashed password
    MySQL-->>Auth: User record
    Auth-->>Client: 204 No Content + Session Cookie (laravel_session)
```

### 3.2 Feed Query with Hybrid Batch Loading

```mermaid
sequenceDiagram
    autonumber
    participant Client as Nuxt 4 Client
    participant LH as Lighthouse GraphQL Engine
    participant Feed as FeedQuery Resolver
    participant Mongo as MongoDB (Posts)
    participant TF as PostTransformer Batcher
    participant MySQL as MySQL (Users & Metadata)

    Client->>LH: POST /graphql { query: feed(page: 1) { ... } }
    LH->>LH: Verify session authentication (@guard)
    LH->>Feed: Invoke FeedQuery::__invoke()
    Feed->>Mongo: Post::latest()->paginate(15)
    Mongo-->>Feed: 15 Post documents
    Feed->>TF: transformPosts(posts)
    Note over TF,MySQL: Batch loading to prevent N+1 queries
    TF->>MySQL: User::whereIn('id', uniqueUserIds)
    TF->>MySQL: PostMetaData::whereIn('post_id', postIds)
    TF->>MySQL: PostLike::whereIn('post_id', ...)->where('user_id', current)
    MySQL-->>TF: Authors, Metadata, Liked flags
    TF-->>Feed: Fully hydrated post collection
    Feed-->>LH: PostPaginator payload
    LH-->>Client: JSON { data: { feed: { data: [...], pagination: {...} } } }
```

### 3.3 Post Creation with Hashtags

```mermaid
sequenceDiagram
    autonumber
    participant Client as Nuxt 4 Client
    participant LH as Lighthouse GraphQL Engine
    participant PM as PostMutation@create
    participant Service as PostService
    participant Mongo as MongoDB (Posts)
    participant MySQL as MySQL (PostMetaData)

    Client->>LH: mutation { createPost(content: "Hello #threads") { ... } }
    LH->>PM: Invoke create()
    PM->>Service: createPost("Hello #threads", userId)
    Service->>Service: filterHashTags() -> ["threads"]
    Service->>Mongo: Post::create({ content, tags, user_id })
    Mongo-->>Service: Mongo Post document
    Service->>MySQL: PostMetaData::create({ post_id, user_id })
    MySQL-->>Service: PostMetaData record
    Service-->>PM: Post instance
    PM-->>LH: Hydrated Post
    LH-->>Client: JSON { data: { createPost: { id, content, tags, author } } }
```

### 3.4 Recursive Comment Thread Retrieval

```mermaid
sequenceDiagram
    autonumber
    participant Client as Nuxt 4 Client
    participant LH as Lighthouse GraphQL Engine
    participant CT as CommentThreadQuery
    participant Service as CommentService
    participant Mongo as MongoDB Comments Collection
    participant MySQL as MySQL Users Table

    Client->>LH: query { commentThread(id: "...") { id content author replying_to } }
    LH->>CT: Invoke CommentThreadQuery::__invoke()
    CT->>Service: getThread(rootCommentId)
    Service->>Mongo: $graphLookup (maxDepth: 50, connectTo: parent_id)
    Mongo-->>Service: Flat array of nested descendant comments
    Service->>MySQL: User::whereIn('id', threadUserIds)
    MySQL-->>Service: Author profiles
    Service->>Service: Map parent authors to replying_to metadata
    Service-->>CT: Chronologically sorted comment thread
    CT-->>LH: [Comment!]
    LH-->>Client: JSON { data: { commentThread: [...] } }
```
