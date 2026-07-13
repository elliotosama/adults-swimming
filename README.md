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

when i filter creation date from only and employee all receipts creations or update
when i filter new and renew filters
branches
employees

run this on the server => {
  mkdir -p public/uploads/captains_ids
  chmod 755 public/uploads/captains_ids
}