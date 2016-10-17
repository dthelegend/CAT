CREATE TABLE IF NOT EXISTS `silverbullet_user` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '',
  `inst_id` INT(11) NOT NULL COMMENT '',
  `username` VARCHAR(45) NOT NULL COMMENT '',
  PRIMARY KEY (`id`, `inst_id`)  COMMENT '',
  INDEX `fk_user_institution1_idx` (`inst_id` ASC)  COMMENT '',
  CONSTRAINT `fk_user_institution1`
    FOREIGN KEY (`inst_id`)
    REFERENCES `institution` (`inst_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `silverbullet_certificate` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '',
  `inst_id` INT(11) NOT NULL COMMENT '',
  `silverbullet_user_id` INT(11) NOT NULL COMMENT '',
  `one_time_token` VARCHAR(45) NOT NULL COMMENT '',
  `token_expiry` TIMESTAMP NOT NULL COMMENT '',
  `expiry` TIMESTAMP NULL DEFAULT NULL COMMENT '',
  `document` BLOB NULL COMMENT '',
  PRIMARY KEY (`id`, `inst_id`, `silverbullet_user_id`)  COMMENT '',
  INDEX `fk_silverbullet_certificate_silverbullet_user1_idx` (`silverbullet_user_id` ASC, `inst_id` ASC)  COMMENT '',
  CONSTRAINT `fk_silverbullet_certificate_silverbullet_user1`
    FOREIGN KEY (`silverbullet_user_id` , `inst_id`)
    REFERENCES `silverbullet_user` (`id` , `inst_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8;