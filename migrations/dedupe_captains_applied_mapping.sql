-- Exact captain duplicate merge mapping applied to the local database.
-- This file avoids CREATE TABLE privileges: it only repoints references,
-- removes duplicate pivot rows, then deletes duplicate captain rows.

START TRANSACTION;

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'c-110' FROM captain_branch WHERE captain_id = 'm-58';
UPDATE receipts SET captain_id = 'c-110' WHERE captain_id = 'm-58';
DELETE FROM captain_branch WHERE captain_id = 'm-58';
DELETE FROM captains WHERE id = 'm-58';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'c-120' FROM captain_branch WHERE captain_id = 'm-174';
UPDATE receipts SET captain_id = 'c-120' WHERE captain_id = 'm-174';
DELETE FROM captain_branch WHERE captain_id = 'm-174';
DELETE FROM captains WHERE id = 'm-174';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'c-22' FROM captain_branch WHERE captain_id = 'm-220';
UPDATE receipts SET captain_id = 'c-22' WHERE captain_id = 'm-220';
DELETE FROM captain_branch WHERE captain_id = 'm-220';
DELETE FROM captains WHERE id = 'm-220';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'c-92' FROM captain_branch WHERE captain_id = 'm-210';
UPDATE receipts SET captain_id = 'c-92' WHERE captain_id = 'm-210';
DELETE FROM captain_branch WHERE captain_id = 'm-210';
DELETE FROM captains WHERE id = 'm-210';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-100' FROM captain_branch WHERE captain_id = 'm-147';
UPDATE receipts SET captain_id = 'm-100' WHERE captain_id = 'm-147';
DELETE FROM captain_branch WHERE captain_id = 'm-147';
DELETE FROM captains WHERE id = 'm-147';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-107' FROM captain_branch WHERE captain_id = 'm-106';
UPDATE receipts SET captain_id = 'm-107' WHERE captain_id = 'm-106';
DELETE FROM captain_branch WHERE captain_id = 'm-106';
DELETE FROM captains WHERE id = 'm-106';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-123' FROM captain_branch WHERE captain_id = 'm-95';
UPDATE receipts SET captain_id = 'm-123' WHERE captain_id = 'm-95';
DELETE FROM captain_branch WHERE captain_id = 'm-95';
DELETE FROM captains WHERE id = 'm-95';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-129' FROM captain_branch WHERE captain_id = 'm-96';
UPDATE receipts SET captain_id = 'm-129' WHERE captain_id = 'm-96';
DELETE FROM captain_branch WHERE captain_id = 'm-96';
DELETE FROM captains WHERE id = 'm-96';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-133' FROM captain_branch WHERE captain_id = 'm-97';
UPDATE receipts SET captain_id = 'm-133' WHERE captain_id = 'm-97';
DELETE FROM captain_branch WHERE captain_id = 'm-97';
DELETE FROM captains WHERE id = 'm-97';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-140' FROM captain_branch WHERE captain_id = 'm-89';
UPDATE receipts SET captain_id = 'm-140' WHERE captain_id = 'm-89';
DELETE FROM captain_branch WHERE captain_id = 'm-89';
DELETE FROM captains WHERE id = 'm-89';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-143' FROM captain_branch WHERE captain_id = 'm-90';
UPDATE receipts SET captain_id = 'm-143' WHERE captain_id = 'm-90';
DELETE FROM captain_branch WHERE captain_id = 'm-90';
DELETE FROM captains WHERE id = 'm-90';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-154' FROM captain_branch WHERE captain_id = 'm-93';
UPDATE receipts SET captain_id = 'm-154' WHERE captain_id = 'm-93';
DELETE FROM captain_branch WHERE captain_id = 'm-93';
DELETE FROM captains WHERE id = 'm-93';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-179' FROM captain_branch WHERE captain_id = 'm-180';
UPDATE receipts SET captain_id = 'm-179' WHERE captain_id = 'm-180';
DELETE FROM captain_branch WHERE captain_id = 'm-180';
DELETE FROM captains WHERE id = 'm-180';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-207' FROM captain_branch WHERE captain_id = 'm-223';
UPDATE receipts SET captain_id = 'm-207' WHERE captain_id = 'm-223';
DELETE FROM captain_branch WHERE captain_id = 'm-223';
DELETE FROM captains WHERE id = 'm-223';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-209' FROM captain_branch WHERE captain_id = 'm-224';
UPDATE receipts SET captain_id = 'm-209' WHERE captain_id = 'm-224';
DELETE FROM captain_branch WHERE captain_id = 'm-224';
DELETE FROM captains WHERE id = 'm-224';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-227' FROM captain_branch WHERE captain_id = 'm-211';
UPDATE receipts SET captain_id = 'm-227' WHERE captain_id = 'm-211';
DELETE FROM captain_branch WHERE captain_id = 'm-211';
DELETE FROM captains WHERE id = 'm-211';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-229' FROM captain_branch WHERE captain_id = 'm-213';
UPDATE receipts SET captain_id = 'm-229' WHERE captain_id = 'm-213';
DELETE FROM captain_branch WHERE captain_id = 'm-213';
DELETE FROM captains WHERE id = 'm-213';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-214' FROM captain_branch WHERE captain_id = 'm-233';
UPDATE receipts SET captain_id = 'm-214' WHERE captain_id = 'm-233';
DELETE FROM captain_branch WHERE captain_id = 'm-233';
DELETE FROM captains WHERE id = 'm-233';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-215' FROM captain_branch WHERE captain_id = 'm-236';
UPDATE receipts SET captain_id = 'm-215' WHERE captain_id = 'm-236';
DELETE FROM captain_branch WHERE captain_id = 'm-236';
DELETE FROM captains WHERE id = 'm-236';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-216' FROM captain_branch WHERE captain_id = 'm-238';
UPDATE receipts SET captain_id = 'm-216' WHERE captain_id = 'm-238';
DELETE FROM captain_branch WHERE captain_id = 'm-238';
DELETE FROM captains WHERE id = 'm-238';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-218' FROM captain_branch WHERE captain_id = 'm-239';
UPDATE receipts SET captain_id = 'm-218' WHERE captain_id = 'm-239';
DELETE FROM captain_branch WHERE captain_id = 'm-239';
DELETE FROM captains WHERE id = 'm-239';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-218' FROM captain_branch WHERE captain_id = 'm-230';
UPDATE receipts SET captain_id = 'm-218' WHERE captain_id = 'm-230';
DELETE FROM captain_branch WHERE captain_id = 'm-230';
DELETE FROM captains WHERE id = 'm-230';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-219' FROM captain_branch WHERE captain_id = 'm-240';
UPDATE receipts SET captain_id = 'm-219' WHERE captain_id = 'm-240';
DELETE FROM captain_branch WHERE captain_id = 'm-240';
DELETE FROM captains WHERE id = 'm-240';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-40' FROM captain_branch WHERE captain_id = 'm-36';
UPDATE receipts SET captain_id = 'm-40' WHERE captain_id = 'm-36';
DELETE FROM captain_branch WHERE captain_id = 'm-36';
DELETE FROM captains WHERE id = 'm-36';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-45' FROM captain_branch WHERE captain_id = 'm-61';
UPDATE receipts SET captain_id = 'm-45' WHERE captain_id = 'm-61';
DELETE FROM captain_branch WHERE captain_id = 'm-61';
DELETE FROM captains WHERE id = 'm-61';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-55' FROM captain_branch WHERE captain_id = 'm-52';
UPDATE receipts SET captain_id = 'm-55' WHERE captain_id = 'm-52';
DELETE FROM captain_branch WHERE captain_id = 'm-52';
DELETE FROM captains WHERE id = 'm-52';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-56' FROM captain_branch WHERE captain_id = 'm-53';
UPDATE receipts SET captain_id = 'm-56' WHERE captain_id = 'm-53';
DELETE FROM captain_branch WHERE captain_id = 'm-53';
DELETE FROM captains WHERE id = 'm-53';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-54' FROM captain_branch WHERE captain_id = 'm-57';
UPDATE receipts SET captain_id = 'm-54' WHERE captain_id = 'm-57';
DELETE FROM captain_branch WHERE captain_id = 'm-57';
DELETE FROM captains WHERE id = 'm-57';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-102' FROM captain_branch WHERE captain_id = 'm-103';
UPDATE receipts SET captain_id = 'm-102' WHERE captain_id = 'm-103';
DELETE FROM captain_branch WHERE captain_id = 'm-103';
DELETE FROM captains WHERE id = 'm-103';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-121' FROM captain_branch WHERE captain_id = 'm-94';
UPDATE receipts SET captain_id = 'm-121' WHERE captain_id = 'm-94';
DELETE FROM captain_branch WHERE captain_id = 'm-94';
DELETE FROM captains WHERE id = 'm-94';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-152' FROM captain_branch WHERE captain_id = 'm-149';
UPDATE receipts SET captain_id = 'm-152' WHERE captain_id = 'm-149';
DELETE FROM captain_branch WHERE captain_id = 'm-149';
DELETE FROM captains WHERE id = 'm-149';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-150' FROM captain_branch WHERE captain_id = 'm-153';
UPDATE receipts SET captain_id = 'm-150' WHERE captain_id = 'm-153';
DELETE FROM captain_branch WHERE captain_id = 'm-153';
DELETE FROM captains WHERE id = 'm-153';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-173' FROM captain_branch WHERE captain_id = 'm-175';
UPDATE receipts SET captain_id = 'm-173' WHERE captain_id = 'm-175';
DELETE FROM captain_branch WHERE captain_id = 'm-175';
DELETE FROM captains WHERE id = 'm-175';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-189' FROM captain_branch WHERE captain_id = 'm-190';
UPDATE receipts SET captain_id = 'm-189' WHERE captain_id = 'm-190';
DELETE FROM captain_branch WHERE captain_id = 'm-190';
DELETE FROM captains WHERE id = 'm-190';

INSERT IGNORE INTO captain_branch (branch_id, captain_id) SELECT branch_id, 'm-243' FROM captain_branch WHERE captain_id = 'm-242';
UPDATE receipts SET captain_id = 'm-243' WHERE captain_id = 'm-242';
DELETE FROM captain_branch WHERE captain_id = 'm-242';
DELETE FROM captains WHERE id = 'm-242';

COMMIT;
