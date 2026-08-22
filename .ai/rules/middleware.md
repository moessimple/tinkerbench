---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## EnsureRequestIsLocal also covers herd share tunnels, via TrustProxies

EnsureRequestIsLocal checks `$request->ip()` against the loopback addresses. That alone would miss a request tunneled in via `herd share`: Expose terminates the real remote connection itself and forwards it to Herd's nginx locally, so nginx always sees 127.0.0.1 as the peer, whether the visitor is genuinely local or came in through the tunnel. Confirmed empirically: without proxy trust configured, the public share URL returned 200, not 403.

This is closed, not just accepted, by trusting `127.0.0.1`/`::1` for `X-Forwarded-For` in `bootstrap/app.php` (`$middleware->trustProxies(...)`), so `$request->ip()` resolves to the tunneled visitor's real IP instead. This is safe specifically because of two things confirmed for this setup, don't assume they hold elsewhere without re-checking:

- Herd's nginx binds only to `127.0.0.1` (checked its generated config), so a direct LAN attacker can't reach it at all to set their own X-Forwarded-For.
- Expose's edge (fronted by Caddy) sets X-Forwarded-For from the real connection and discards whatever the client sends: verified by curling the share URL with a spoofed `X-Forwarded-For: 127.0.0.1` header and confirming the app still saw the real IP, not the spoofed one.

`headers:` is scoped to `Request::HEADER_X_FORWARDED_FOR` only, not Laravel's broader default (which also trusts X-Forwarded-Host/Port/Proto), to avoid an unrelated request-host change from a tunnel visit affecting anything else in the app.
