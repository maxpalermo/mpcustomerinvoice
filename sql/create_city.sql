DROP TABLE IF EXISTS `{PFX}city`;
CREATE TABLE IF NOT EXISTS `{PFX}city` (
  `id_brtloc` int(11) NOT NULL AUTO_INCREMENT,
  `postcode` varchar(5) NOT NULL,
  `city` varchar(128) NOT NULL,
  `state` varchar(128) NOT NULL,
  `iso_code` char(2) NOT NULL,
  `status` char(1) NOT NULL,
  `date_add` datetime NOT NULL,
  `date_upd` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_brtloc`),
  UNIQUE KEY `unique_city` (`city`,`iso_code`)
) ENGINE=InnoDB AUTO_INCREMENT=35535;
