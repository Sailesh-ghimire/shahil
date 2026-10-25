# Workspace CRM — user guide

Everything you do happens inside a **workspace** (your business or team). The switcher at the top of the
sidebar changes workspace; nothing from one workspace is ever visible in another. Your role can differ per
workspace.

## Getting started (owners)

1. **Sign up** at `/app/register`, verify your email, then **create a workspace**: name, currency, timezone.
2. **Workspace settings** (workspace menu → Workspace settings): name, web address, currency, timezone,
   follow-up rule for idle deals, monthly revenue target.
3. **Invite people** (Settings → Members → Invite member). They get an email link; after verifying their
   email they join with the role you chose. Only an Owner can make someone Owner.
4. **Roles** (Settings → Roles): five built-in roles (Owner, Admin, Manager, Sales rep, Viewer). Create your
   own from the permission checklist. The Owner role can't be changed, and a workspace always keeps one Owner.
5. **Pipelines** (Settings → Pipelines): stages, win probability, and exactly one Won and one Lost stage.
6. **Products** (Catalog → Products): SKU, price, tax (13% VAT by default), active/inactive.
7. **Channels** (Settings → Channels): connect WhatsApp, a Facebook Page and Instagram (see below).

## Customers

- **Companies** and **Contacts**. A contact can have several emails, phones and social handles (one marked
  primary) and can work at several companies, each with its own job title. Phone numbers are saved in
  international format (+977…) so WhatsApp messages match the right person.
- Each record has tabs: **Overview**, **Deals**, **Tasks**, **Timeline** (calls, meetings, notes, messages),
  and **History** (who changed what; Admins and Owners only).
- **Log activity** records a call, meeting, note or email. **Add follow-up** schedules a task with a reminder.
- **Import / export**: CSV or Excel (.xlsx; the first sheet is used). Map columns, choose what happens when a
  row matches an existing record (update, skip or create new), then start. You get a notification with
  created / updated / skipped / failed counts and a download of failed rows with the reason for each.
  Contacts match by email, then phone; companies by name; products by SKU.
- Deleted records can be restored for 30 days (filter **Deleted records**), then they are removed for good.

## Sales

- **Leads** (Sales → Leads, or **Board**): New, Contacted, Qualified, Unqualified. Drag a card to change its
  status; dropping on Unqualified asks why. On a phone, use the dropdown on each card instead of dragging.
  New leads without an owner are shared out in turn between sales reps.
- **Convert** a qualified lead: it becomes (or is linked to) a contact and company, and a deal opens in the
  first stage. Converted leads become read-only.
- **Deals** (Sales → Deals, or **Board**): one column per stage with count and total value. Dropping on Won
  asks for the close date; Lost asks for a reason. Every move can be undone from the notification.
  A red "Nd idle" means no activity for 7+ days.
- **Products tab** on a deal: add products with quantity and discount; the deal value is the total.

## Your day

- **Dashboard**: follow-ups due today (and overdue), unread messages, your new leads, your pipeline.
- **Tasks & follow-ups**: Today, Overdue, Upcoming, Completed. Mark done, snooze an hour, move to tomorrow.
- **Reminders** arrive in the bell, by email, and as a pop-up while the CRM is open — with **Mark done** and
  **Snooze 1 hour** buttons. Times are shown in your own timezone (set it in your profile).
- If a deal sits idle longer than the workspace setting, a follow-up is created for its owner automatically.

## Inbox (WhatsApp, Instagram, Facebook)

- **Engage → Inbox** shows every conversation. Filter by All, Assigned to me, Unassigned, Unread, or channel.
- A message from someone new creates a lead automatically and assigns the chat. Messages from known customers
  are linked to their contact and appear on their timeline.
- **Reply** in the box (Enter sends, Shift+Enter adds a line). Ticks show sent, delivered and read.
- **WhatsApp 24-hour rule**: more than 24 hours after the customer's last message you can only send an
  **approved template** (Meta charges for these). Sync templates in Settings → Channels.
- Side panel: **Create lead**, **Link to contact**, **Assign** / **Assign to me**.
- Sales reps can reply to conversations assigned to them; managers and admins to any.

## Connecting channels (owners and admins)

Meta's approval for one-click sign-up can take weeks. Until then each account is connected with its id and an
access token from your Meta app (Settings → Channels → Connect account). The page also lists the webhook
addresses to enter in the Meta app. If a token expires, the account shows **Reconnect needed** and channel
managers get an email; use **Reconnect** to paste a new token.

## Reports (Insights → Reports)

Pick dates, owner and pipeline; every chart follows. Revenue won vs target, pipeline by stage, lead sources,
won vs lost by rep, lost reasons, activity mix, rep scorecard, idle big deals, first reply time by channel,
the lead-to-won funnel, and a list of stale deals with **Add follow-up**. Sales reps see their own numbers.
Figures refresh every 10 minutes.

## Roles at a glance

| Can… | Owner | Admin | Manager | Sales rep | Viewer |
| --- | --- | --- | --- | --- | --- |
| Workspace settings | Yes | No | No | No | No |
| Invite members, manage roles, connect channels | Yes | Yes | No | No | No |
| Edit pipelines and products | Yes | Yes | Yes | No | No |
| See contacts, companies, leads, deals | All | All | All | All | All |
| Edit contacts, leads, deals | All | All | All | Own | No |
| Convert leads | Yes | Yes | Yes | Yes | No |
| Delete, import, export | Yes | Yes | Yes | No | No |
| Reply in the inbox | Any | Any | Any | Assigned | No |
| Reports | Team | Team | Team | Own | Team |
| Audit log | Yes | Yes | No | No | No |
