# ************************************************************
# Database: blockchain
# ************************************************************

DROP TABLE IF EXISTS `blocks`;
CREATE TABLE `blocks` (
  `block_id` varbinary(64) NOT NULL DEFAULT '',
  `previous` varbinary(64) NOT NULL DEFAULT '',
  `hash_list_root` varbinary(64) NOT NULL DEFAULT '',
  `time` bigint(64) unsigned NOT NULL,
  `target` varbinary(64) NOT NULL DEFAULT '',
  `chain_work` varbinary(64) NOT NULL DEFAULT '',
  `nonce` bigint(64) unsigned NOT NULL,
  `height` bigint(64) unsigned NOT NULL,
  `transaction_count` int(32) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`height`),
  UNIQUE KEY `block_id` (`block_id`)
);


DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `transaction_id` varbinary(64) NOT NULL DEFAULT '',
  `time` bigint(64) NOT NULL,
  `nonce` int(32) NOT NULL,
  `from` varbinary(44) DEFAULT NULL,
  `to` varbinary(44) NOT NULL DEFAULT '',
  `amount` bigint(64) DEFAULT NULL,
  `fee` bigint(64) DEFAULT NULL,
  `memo` varchar(100) DEFAULT NULL,
  `matures` bigint(64) DEFAULT NULL,
  `expires` bigint(64) DEFAULT NULL,
  `series` bigint(64) NOT NULL,
  `signature` varbinary(128) DEFAULT NULL,
  PRIMARY KEY (`transaction_id`)
);

DROP TABLE IF EXISTS `block_transactions`;
CREATE TABLE `block_transactions` (
  `height` bigint(64) unsigned NOT NULL,
  `block_id` varbinary(64) NOT NULL DEFAULT '',
  `transaction_id` varbinary(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`block_id`,`transaction_id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `block_transactions_ibfk_1` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`block_id`),
  CONSTRAINT `block_transactions_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`transaction_id`)
);

INSERT INTO `blocks`
(`block_id`, `previous`, `hash_list_root`, `time`, `target`, `chain_work`, `nonce`, `height`, `transaction_count`)
VALUES
(
    X'30303030303030306532396137383530303838643636303438396237623961653264613736336263336264383333323465636335346565653034383430616462',
    X'30303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030',
    X'37616662383937303533313662336465373961333838326563333733326236623837393664643462663261383032343035343961653866643439613531376438',
    1561173156,
    X'30303030303030306666666630303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030',
    X'30303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030303030313030303130303031',
    1695541686981695,
    0,
    1
);

INSERT INTO `transactions`
(`transaction_id`, `time`, `nonce`, `from`, `to`, `amount`, `fee`, `memo`, `matures`, `expires`, `series`, `signature`)
VALUES
(
    X'62613830303964656133656665383231363532666438323031323632623031656366363665316331623737616534633161616161373532353064363937383962',
    1561173126,
    1654479747,
    NULL,
    X'6E746B536262472B6230766F3439494764396E6E48333965484978494571586D494C3861614A5A562B6A513D',
    5000000000,
    NULL,
    '0000000000000000000de6d595bddae743ac032b1458a47ccaef7b0f6f1e3210',
    NULL,
    NULL,
    1,
    NULL
);

INSERT INTO `block_transactions`
(`height`, `block_id`, `transaction_id`)
VALUES
(
    0,
    X'30303030303030306532396137383530303838643636303438396237623961653264613736336263336264383333323465636335346565653034383430616462',
    X'62613830303964656133656665383231363532666438323031323632623031656366363665316331623737616534633161616161373532353064363937383962'
);
