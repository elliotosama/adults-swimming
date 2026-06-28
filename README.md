# Auth
- [x] login (show form)
- [x] login (authenticate)
### should reject invalid credentials with a clear error
- [x] logout
- [x] register (show form)
### no POST handler exists yet for register — submission isn't wired up
---
# Dashboard
- [x] admin dashboard
- [ ] branch_manager dashboard
- [x] customer_service dashboard
- [x] area_manager dashboard
---
# Countries
- [x] create
- [x] update
- [x] delete
### delete is a GET route, not POST — no CSRF protection, can be triggered by just clicking a link
---
# Prices
- [x] create
- [x] read
- [x] update
- [x] delete
---
# Branches
- [ ] create
### start time should be earlier than end time
- [ ] read
### does not show the country of the branch
- [x] update
- [x] delete
---
# Captains
- [x] create
- [x] read
- [x] update
- [x] delete
---
# Employees
- [x] create
- [x] read
- [x] update
- [x] delete
---
# Clients
- [x] create
- [x] read
- [x] update
- [x] visible
- [ ] delete
### does not delete the user from the database
---
# Receipts
- [x] create
- [ ] read (list)
- [x] show (single receipt)
- [ ] update
### update does not update audit log
- [x] delete
- [ ] export
- [x] preview
- [x] pdf generation
### it generate the pdf but it does not store it on the server
- [x] renew (show page)
- [x] renew (store renewal)
- [x] payment (show page)
- [x] refund (show page)
- [x] refund (store refund)
- [x] manage page
- [x] search-json (AJAX search)
- [x] send-email
- [x] payment-by-id (show)
- [x] payment-by-id (store)
---
# Transactions
- [ ] create
### check the form and controller function
- [x] read (list)
- [x] show
- [x] update
- [x] delete
- [x] remove evidence
