admin => rahma@gmail.com : fJ@S8@UM
customer service => naira.elbanna@gmail.com : 9tYB$4Wv
ramez.mohamed@gmail.com : cpkQ@mm9


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



- [x] error message appear twice in the top of create tab
- [x] the branch work from 8 PM to 12 am and i choose 8 PM and it appear error message
- [x] in index search using receipt number and client phone number and client id and client name
- [x] in index make search in ajax
- [x] payment and refund and client tabs does not appear








receipt index {
- [x] show only created receipt
- [x] edit filter from receipt pages
}


preview {
- [x] show age in preview page
- [x] creator of the receipt in preview page creation date
- [x] notes in preview
- [x] change تاريح البدايه تاريخ النهايه تاريخ التجدد
- [x] font should be bold in preview and bigger
}
- [x] change الاشتراك ان الاخطه
- [x] payment should be receipt id or phone number
- [x] active and inactive in branches
- [x] branches in edit in captains
- [x] employees button 
- [x] remove delete and edit from index screen in employees page
- [x] remove delete and edit from index screen in prices page
- [x] check disabled branches in receipt page
- [x] change المعاملات ل المعاملات الماليه
- [x] اضافه ة بدلا من ه
- [x] update default value from level to 0


customer service account
- [x] remove updates from receipt index
  not update plan{
  time => Done
  start  => Done
  branch => Done
  captain => Done
}


area manager {
  remove disable branch
  add audit log in branches update and show them in admin dashboard
  remove disable from captain
}


branch manager {
  start date
  captain
  excersice time
  level
}





captains table {
  arabic encoding
}



- [ ] remove receipt transactions for branch manger and customer service and area manager
- [ ] check create client
- [ ] check when the admin update paid amount in update receipts
- [ ] ui in receipts  
- [x] make branch_manager can add captain but can not remove it
- [ ] check the user 01018043530 has receipts on the old system or not because it created this receipt yasterday and now can not make a payment because receipt not found
- [x] replace receipt id in receipt pdf to client id
- [x] make branch manager can edit level captain and first session and exercise time only
- [ ] when i filter with 11/7 and 10/7 i got the same receipts but in 10 i got one more
- [ ] complete admin functionality on the server
- [x] add captain functionality for branch manger
- [x] another payment in receipt notes
- [x] check update functionality because it remove the old data
- [x] success message after editing receipt
- [x] in edit add option to add another payment evidence and only admin can remove the payment evidence
- [x] when i edit receipt it removes filters in index page
- [x] check 01125260919 receipt because it created two times
- [ ] check 01102816922 receipt because it created two times
- [ ] remove height from receipts index page
- [ ] filters in receipts




when i filter 11 or 10 => 


- [ ] creation date
- [ ] transactions and update

when i filter new and renew filters
branches
employees

run this on the server => {
  mkdir -p public/uploads/captains_ids
  chmod 755 public/uploads/captains_ids
}




---------------------------------------------------

26070463 => does not exists in the new database


5886
5887
دفع ايصال
تعديل ايصال


update_receipt_creation_dates.php --report => 5887
update_receipt_creation_dates.php --apply --report



--------
receipt edits => php compare_receipt_edit_history.php --old-db=swimmingacademy


php sync_receipt_edit_history_audit_logs.php --old-db=swimmingacademy --report --apply
php migration_checks/remove_duplicate_receipt_audit_logs.php --old-db=swimmingacademy --report --apply

9/7
in old system => 45
in new system => 52

8/7
in old system => 66
in new system => 72



summary for edit_history migration
--- Summary ---
Total history entries seen: 12365
Inserted: 12323
Skipped (already existed): 42
Skipped (bad/missing data): 0
Rows with unresolved/unmatched editor (role='unknown'): 125
Skipped (FK/constraint violation on insert): 0





paid => 1000
another paiment => 770
refund => 700
refund - 539
total paid 531



- [x] zied receipt  (1018043530) does not appear
- [ ] migrate creation date from old website
- [ ] migrate payment evidences from old database
- [x] statistics in modal page
- [ ] check edits from old website
- [x] mohamed receipt (01285019607) receipt does not exists
- [ ] payment method and level and email does not selected by default
- [ ] confirm update message does not appear
- [ ] check upload another evidence functionality
- [ ] why both made new at the same day
- [ ] spaces in index file
- [ ] add client name in receipt name
- [ ] logo should appear when you share the pdf
- [ ] filters
- [ ] check current and previous renewal



---


- [x] when i filter with 11 get 10 receipts
- [x] reciept created dupllicates => remove them using query
- [ ] include client name in receipt url and make the logo appearn when i send it whatsapp



- [ ] check confirmation message when create or renew
- [ ] check access in edit
- [ ] check edit functionality
- [x] pay less than 400
- [x] receipts made with zero
- [x] can accept zero
- [x] phone number and whatsapp error
- [ ] renewal type in receipts => when makding database migration
- [ ] in excel appear client id and plan price
- [search works] does not make search with client names
- [ ] don't appear disabled employees and branches in index page in receipts
- [ ] make the box of branches bigger
- [ ] check action buttons in branches employees and captains pages
- [ ] filters
- [ ] search with captains in receipts
- [ ] captain nickname appear when create or renew 