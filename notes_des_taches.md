ALTER TABLE `eleve` ADD `matricule` VARCHAR(20) NOT NULL AFTER `id`;
ALTER TABLE `eleve` ADD `nationalite` VARCHAR(20) NOT NULL AFTER `menage`;
ALTER TABLE `menage` ADD `province` VARCHAR(20) NOT NULL AFTER `id_original`;
UPDATE eleve SET nationalite = 'CONGOLAISE';
UPDATE menage SET province = 'KINSHASA';
UPDATE appel SET anneeScolaire = '2026-2027';
UPDATE appel SET anneeScolaire = '2026-2027';