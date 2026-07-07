- [x] remove delete button from every account => Done
- [x] add button in manage page to download receipt directly => Done
- [x] check working time in renewal tab => Done
- [x] the number of total receipts i in arabic in receipts page => Done
- [x] icon is just an icon
- [x] i don't want the system to stomre renewal type by it's own i just want to validate it and the user choose renwal type and if its wrong i want the system rais error message => Done
- [x] when i search with branch manager account it should get the users from this branch only => Done
- [x] in renewal tab make the search with client id or phone number only => Done
- [x] renewal type in receipts page should be in arabic => Done
- [x] add validation if email exists => Done
- [x] show the image in the same page in show receipt page => Done



- [ ] if i choose cash remove upload
- [ ] error message appear twice
- [ ] please login message appear after login

osamaitech123456@gmail.com



admin => rahma@gmail.com : fJ@S8@UM
area manager => abdullah.ezzat@gmail.com : PD#pi87h
customer service => naira.elbanna@gmail.com : 9tYB$4Wv
branch manager => abdullah.ezaat.2@gmail.com : $mJvM9Fn

- [x] in refund should appear the last receipt only
- [x] can not make another payment on a refunded receipt
- [x] check if this email or phone number has exist aleardy in a new receipt or not if exists rais error message to tell him this client has a new receipt already
- [x] upload evidance required in all payment method except cash and don't appear cash in customer service account
- [x] in refund searching using receipt id does not work
- [x] in payment search should be phone number or receipt id
- [x] check receipt type when renew a receipt "if my last receipt end date is 18/4 and i renewd a receipt in 20/4 it will be current renewal but if the renewed at 21/4 it will be previous renewal becuase my month ends at 21
- [x] in show receipt show remaining and paid amount
# bugs in pdf
---
- [ ] ajax in transactions page
- [ ] check search page and font in all pages
- [ ] in login make the form more bigger




- [x] session start and session end in receipt page
- [x] make them white
- [x] font should be the same in all page
- [x] admin can change it's password


- [ ] change background color
- [ ] branches drop down in receipt
- [ ] filters should be in the same line
- [x] make text in the table in the middle
-----------------------------
when making a receipt [Mon Jul 06 12:45:47.358379 2026] [php:error] [pid 1880044] [client 102.184.85.96:34940] PHP Fatal error:  Uncaught PDOException: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '' for key 'email' in /var/www/swimming-academy/app/controllers/ReceiptController.php:890\nStack trace:\n#0 /var/www/swimming-academy/app/controllers/ReceiptController.php(890): PDOStatement->execute()\n#1 /var/www/swimming-academy/app/controllers/ReceiptController.php(999): ReceiptController->findOrCreateClient()\n#2 /var/www/swimming-academy/public/index.php(132): ReceiptController->store()\n#3 /var/www/swimming-academy/public/index.php(218): {closure}()\n#4 {main}\n  thrown in /var/www/swimming-academy/app/controllers/ReceiptController.php on line 890, referer: http://92.205.25.104/receipt/manage
- [ ]in refund show the percentage of refund
------------------------------





16746
+201273521512
26071255




📅 آخر جلسة: 2026-06-18 => it should be previous renewal because we exceed 21/6 but it gives me the correct is current renewal



- [ ] error message appear twice in the top of create tab
- [ ] the branch work from 8 PM to 12 am and i choose 8 PM and it appear error message
- [x] in index search using receipt number and client phone number and client id and client name
- [x] in index make search in ajax
- [x] payment and refund and client tabs does not appear
