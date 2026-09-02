CREATE TABLE IF NOT EXISTS `lead_followup_history` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` INT NOT NULL,
    `next_followup_date` DATE NOT NULL,
    `comment` TEXT NOT NULL,
    `status_id` INT NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `lead_id` (`lead_id`),
    KEY `status_id` (`status_id`),
    KEY `next_followup_date` (`next_followup_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


lead created date to and lead created from search 


lead entry che ama ma start and end date mukvanu che 
lead edit pachi main page ma avi jay che filter jata rhe che
deal done ma amount batave che amount amuk jagya a nathi avta 


 
