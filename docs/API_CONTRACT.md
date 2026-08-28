# API Contract

> Base URL: `http://localhost:8000` (dev) — all paths below are absolute.
> Auth unless noted otherwise. Content-Type `application/json`. Pagination via `?page=&per_page=` (clamped `1..∞` and `1..50`).

## 1. Conventions

### 1.1 Authentication

* `/auth/*` — **session** (web guard, Breeze). `guest` for register/login/forgot, `auth`+`signed` for verify, `auth` for logout/verification-notification.
* `/api/v1/*` — **Sanctum Bearer** (`Authorization: Bearer <token>`) except `GET|POST /api/v1/waf-test` and `GET /api/ping-mongodb` which are public.
* `GET /api/up` — health (`bootstrap/app.php: health`).

### 1.2 Envelope

Success (via `App\Utils\Http::success`):
```json
{ "success": true, "message": "…", "data": { } }
```
`message` omitted when `null`. `201` for creates, `204` no-content for updates/deletes.

Error:
```json
{ "success": false, "message": "Not found", "code": 404 }
```
Validation errors use Laravel default `422` with `errors`/`message`.

Paginated helpers return:
```json
{
  "success": true,
  "data": {
    "posts": [ /* transformed */ ],
    "pagination": { "total": 100, "per_page": 15, "current_page": 1, "last_page": 7, "from": 1, "to": 15 }
  }
}
```
Some services paginate via `LengthAwarePaginator` and unwrap `posts` similarly.

### 1.3 Common Headers

SecurityHeaders adds: `Strict-Transport-Security`, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store …` for `api/*`, `Content-Security-Policy` in production.
WAF may return `403 {message:"Request blocked by Web Application Firewall", reason?, details?}` or `429 {message:"Too many requests", retry_after}`.

## 2. Endpoints

### 2.1 Auth — `/auth` (web, session)

| Method | Path | Guard | Body | Success |
|--------|------|-------|------|---------|
| POST | `/auth/register` | guest | `{name, username, email, password, password_confirmation}` | 204 (RegisteredUserController) |
| POST | `/auth/login` | guest | `{email, password}` | 204 + session cookie (LoginRequest) |
| POST | `/auth/logout` | auth | — | 204 |
| POST | `/auth/forgot-password` | guest | `{email}` | 200 (PasswordResetLinkController) |
| POST | `/auth/reset-password` | guest | `{token, email, password, password_confirmation}` | 200 |
| GET | `/auth/verify-email/{id}/{hash}` | auth,signed,throttle:6,1 | — | redirect/signed verify |
| POST | `/auth/email/verification-notification` | auth,throttle:6,1 | — | 202 |

Validation: `RegisteredUserRequest` enforces unique `username`/`email`, password rules; `LoginRequest` throttles and `authenticate()`.

### 2.2 Utility — public

| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET\|POST | `/api/v1/waf-test` | no | `200 {message:"WAF Test Endpoint", timestamp, waf_enabled, waf_mode}` — used by `WafTest` as probe; never reflects request data |
| GET | `/api/ping-mongodb` | no | `200 {msg:"Pinged your deployment…"}` or `500 {msg:"MongoDB connection failed"}` / `500 {msg:"MongoDB is not configured"}` — generic message, details logged |

### 2.3 Profile — `/api/v1/me` (auth)

| Method | Path | Controller | Notes |
|--------|------|------------|-------|
| GET | `/me/profile` | `ProfileController@myProfile` → `UserService::getAuthUser` | `{id, name, username, email, avatar, bio, email_verified}` |
| GET | `/me/reposts` | `myReposts` → `UserService::getAuthUserReposts` | Paginated reposted posts (preserves repost order via `PostService::getRepostedPosts`) |
| GET | `/me/posts` | `PostController@myPosts` | Own posts, `per_page/page` |
| POST | `/me/posts` | `PostController@store` | `PostRequest {content: string max:5000}` → 201 `{post}` |
| GET | `/me/posts/{id}` | `myPost` | Own post by id or 404 |
| PUT\|PATCH | `/me/posts/{id}` | `update` | `PostPolicy@update` + `{content?: string max:5000}` → 204 |
| DELETE | `/me/posts/{id}` | `destroy` | `PostPolicy@delete` → 204 |

### 2.4 Users — `/api/v1/users` (auth)

| Method | Path | Notes |
|--------|------|-------|
| GET | `/users/{username}` | `ProfileController@show` — user + optional posts stream |
| GET | `/users/{username}/posts` | `userPosts` — paginated posts for username |
| GET | `/users/{username}/reposts` | `userReposts` — paginated reposts for userId lookup |

### 2.5 Feed — `/api/v1/posts` (auth)

| Method | Path | Query | Response |
|--------|------|-------|----------|
| GET | `/posts` | `per_page` (default 15, max 50), `page` | `PostService::getFeed` → paginated `PostTransformer::transformPosts` |
| GET | `/posts/{post}` | — | `PostService::getPost` or 404 |

Transformed post shape (via `PostTransformer`):
```json
{
  "id": "ObjectId",
  "content": "hello #world",
  "tags": ["world"],
  "created_at": "2 hours ago",
  "author": { "id": 1, "name": "…", "username": "…", "avatar": "…", "bio": "…" },
  "interactions": { "likes_count": 3, "comments_count": 1, "shares_count": 0, "reposts_count": 0, "liked": true, "reposted": false },
  "metadata": { "likes_count": 3 }
}
```
Batch-loaded users (cached 5m), metadata (`post_meta_data`), liked/reposted flags for `Auth::id()`.

### 2.6 Interactions — `/api/v1/posts/{post}/*` (auth)

| Method | Path | Action | Body | Response |
|--------|------|--------|------|----------|
| POST | `/like` | `PostInteractionController@like` → `LikePostAction` | — | `{liked:true, likes_count}` |
| DELETE | `/like` | `unlike` | — | `{liked:false, likes_count}` |
| GET | `/like/check` | `checkLike` | — | `{liked:bool, likes_count:int}` |
| POST | `/share` | `share` | — | `{shares_count}` |
| POST | `/repost` | `repost` → `RepostPostAction` | — | `{reposted:true, reposts_count}` |
| GET | `/repost/check` | `checkRepost` | — | `{reposted:bool, reposts_count}` |
| GET | `/interactions` | `interactions` | — | `{likes_count, comments_count, shares_count, reposts_count, liked, reposted}` |

Idempotent toggles: like/repost `UNIQUE(post_id,user_id)`.

### 2.7 Comments — `/api/v1` (auth)

| Method | Path | Controller | Validation | Response |
|--------|------|------------|------------|----------|
| GET | `/posts/{id}/comments` | `CommentController@index` → `CommentService::getComments` | — | `{comments:[{id, content, post_id, parent_id, created_at, reply_count, author}]}` 404 if post missing |
| POST | `/posts/{id}/comments` | `store` → `CreateCommentAction` | `{content: required string max:500, parent_id: nullable string}` — parent must exist and `post_id` matches | `201 {comment}`; 404 post/parent, 400 parent-post mismatch |
| GET | `/comments/{id}` | `show` | — | `{comment}` or 404 |
| DELETE | `/comments/{id}` | `destroy` | owner check `user_id === Auth::id()` else 403 | `200 {message:"Comment deleted successfully"}` + decrements `post_meta_data.comments_count` |
| GET | `/comments/{id}/replies` | `replies` | — | `{replies:[…]}` 404 if comment missing |
| GET | `/comments/{id}/thread` | `thread` | — | `200 {thread:[…]}` flat chronological descendants via `$graphLookup` (maxDepth 50), each with `replying_to:{username?}` |

Comment shape: `{id, content, post_id, parent_id, created_at:"… ago", reply_count, replying_to, author:{id,name,username,avatar}}`.

### 2.8 Search — `/api/v1/search` (auth)

`POST /search` → `SearchController@search` → `SearchService::search`

Body:
```json
{ "query": "string required", "page": 1, "per_page": 20 }
```
Query param `?posts=include` toggles post search.

Behaviour:
* `query` sanitized `preg_replace('/[^a-zA-Z0-9\s#]/','',…)`.
* Always searches `users` (`username LIKE %q% OR name LIKE`) limit 10 → `users:[{id,name,username,avatar,bio}]`.
* If `?posts=include`: searches `posts` (`content` regex `/q/i` + `tags` regex, handles `#tag` prefix), paginated `skip/limit`, transformed via `PostTransformer` → `posts`.
* Returns:
```json
{
  "success": true,
  "data": {
    "users": […],
    "posts": […],                // only if ?posts=include
    "search_metadata": { "query": "clean", "total_users": 2, "page":1, "per_page":20 }
  }
}
```

## 3. Validation Summary

* `PostRequest`: `content required|string|max:5000`
* `Comment store`: `content required|string|max:500` + `parent_id nullable|string`
* `RegisteredUserRequest`: `name|string|max:255`, `username|string|max:255|unique`, `email|email|unique`, `password|confirmed|min:8`
* `LoginRequest`: `email|email`, `password|string`
* Search: `query required|string`

## 4. Error Catalog

| Code | When |
|------|------|
| 400 | Parent comment does not belong to post |
| 401 | Missing/invalid Sanctum token on `/api/v1/*` |
| 403 | WAF block, Policy `cannot update/delete`, comment not owned |
| 404 | Post/comment/user not found |
| 422 | Validation fail |
| 429 | WAF rate limit (`retry_after` seconds) |
| 500 | Unexpected (MySQL/Mongo failure, logged, generic message) |

## 5. Versioning

Prefix `v1` is explicit in routes. No `Accept` header versioning. Breaking changes require `v2` prefix.

## 6. Examples

Create post:
```bash
curl -X POST http://localhost:8000/api/v1/me/posts \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"content":"hello #world"}'
# 201 {success:true, data:{post:{id, content, tags:["world"], …}}}
```

Create reply:
```bash
curl -X POST http://localhost:8000/api/v1/posts/$POST_ID/comments \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"content":"nice!", "parent_id":"<comment_id>"}'
```

Search:
```bash
curl -X POST "http://localhost:8000/api/v1/search?posts=include" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"query":"world"}'
```

## 7. Open Contract Notes

* All timestamps returned as `diffForHumans` in transformers (e.g. `2 hours ago`); raw `created_at` available on models.
* `tags` are derived via `HashtagTrait::filterHashTags` on write (not indexed free-text).
* `post_meta_data.post_id` is logical FK to Mongo `_id` (string comparison), not DB-enforced.

