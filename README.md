- [x] search like ajax in all screens
- [x] remove the validation of working time of branches
- [x] time should be in 12 hour format
- [x] in email should put the name of the academy
- [x] check all calculations of money
- [x] show total and paid and refunded money only in review screen
- [x] name of tabs in mobile screen
- [x] font and color in all screen
- [x] receipt pdf
- [ ] the order of fields in receipts screen should be the same as previous website
- [ ] reset all password like in the excel sheet
- [ ] payment evidence does not appear
- [ ] responsive design of all transactions functionalities



Looking at this page, the main responsive challenges are: the wide multi-column table, the filter grid, the page header actions, and the tag-checkbox groups. I'll also bump up table font weight, size, and ensure white text throughout.
Try to copy uploaded file

I'll write the updated file directly with all responsive + typography changes:
Ran 2 commands, read a file
Done

Here's a full breakdown of what changed:

Typography (bolder + bigger + white)

    table td: bumped to font-size: 1rem, font-weight: 600, color: #fff as the base — every cell is now bold and white by default
    table th: font-size: .85rem, font-weight: 800, color: #fff
    Receipt number and plan price cells get font-weight: 800 specifically
    table td strong forced color: #fff; font-weight: 800 so client names are clearly bolder
    Filter group labels bumped to font-weight: 700
