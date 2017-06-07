-- phpMyAdmin SQL Dump
-- version 4.0.9
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Jun 07, 2017 at 12:27 PM
-- Server version: 5.1.41
-- PHP Version: 5.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `bugtracker`
--

-- --------------------------------------------------------

--
-- Table structure for table `history`
--

CREATE TABLE IF NOT EXISTS `history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `issue_id` int(11) NOT NULL,
  `user` varchar(255) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=19 ;

--
-- Dumping data for table `history`
--

INSERT INTO `history` (`id`, `issue_id`, `user`, `date`, `type`, `value`) VALUES
(1, 5, 'IJMacD@gmail.com', '2017-06-07 04:47:58', 'UPDATE', 'a:1:{s:6:"status";s:4:"open";}'),
(2, 5, 'IJMacD@gmail.com', '2017-06-07 04:48:02', 'UPDATE', 'a:1:{s:6:"status";s:6:"closed";}'),
(3, 5, 'IJMacD@gmail.com', '2017-06-07 06:41:41', 'UPDATE', 'a:1:{s:6:"status";s:4:"open";}'),
(4, 5, 'IJMacD@gmail.com', '2017-06-07 06:41:46', 'UPDATE', 'a:1:{s:6:"status";s:6:"closed";}'),
(5, 2, 'IJMacD@gmail.com', '2017-06-07 06:43:30', 'UPDATE', 'a:1:{s:6:"status";s:4:"open";}'),
(6, 2, 'IJMacD@gmail.com', '2017-06-07 06:43:36', 'UPDATE', 'a:1:{s:6:"status";s:6:"closed";}'),
(7, 4, 'IJMacD@gmail.com', '2017-06-07 06:47:12', 'UPDATE', 'a:1:{s:6:"status";s:6:"closed";}'),
(8, 3, 'iain@i-learner.edu.hk', '2017-06-07 06:54:12', 'COMMENT', 'These are my thoughts on the issue: \r\n\r\n1. First thought\r\n2. Second\r\n3. and Third'),
(9, 3, 'IJMacD@gmail.com', '2017-06-07 07:26:30', 'UPDATE', 'a:1:{s:8:"assignee";s:21:"iain@i-learner.edu.hk";}'),
(10, 3, 'IJMacD@gmail.com', '2017-06-07 07:44:43', 'UPDATE', 'a:1:{s:6:"status";s:6:"closed";}'),
(11, 3, 'IJMacD@gmail.com', '2017-06-07 07:52:45', 'UPDATE', 'a:1:{s:6:"status";s:4:"open";}'),
(12, 3, 'IJMacD@gmail.com', '2017-06-07 08:27:28', 'COMMENT', 'I''m making another comment.'),
(13, 6, 'IJMacD@gmail.com', '2017-06-07 08:29:06', 'COMMENT', 'Can I emoji 🍕 here?'),
(14, 0, '8', '2017-06-07 08:41:35', 'CREATE', 'a:3:{s:5:"title";s:20:"Time for a new issue";s:11:"description";s:188:"OK I lied, I tried to add this before. This is a test message.\r\n\r\nIt has some new lines. Some *bold* text.\r\n\r\n* It also has a list\r\n* with <a href="http://www.i-learner.edu.hk">links</a>\r\n";s:7:"creator";s:23:"iain.ilearner@gmail.com";}'),
(15, 7, 'IJMacD@gmail.com', '2017-06-07 08:42:40', 'UPDATE', 'a:1:{s:6:"status";s:6:"closed";}'),
(16, 8, 'IJMacD@gmail.com', '2017-06-07 08:56:31', 'UPDATE', 'a:1:{s:8:"assignee";s:23:"iain.ilearner@gmail.com";}'),
(17, 8, 'IJMacD@gmail.com', '2017-06-07 09:29:13', 'UPDATE', 'a:1:{s:4:"tags";s:15:"Wan Chai, Admin";}'),
(18, 6, 'IJMacD@gmail.com', '2017-06-07 09:31:43', 'UPDATE', 'a:1:{s:4:"tags";s:9:"Website, ";}');

-- --------------------------------------------------------

--
-- Table structure for table `issues`
--

CREATE TABLE IF NOT EXISTS `issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'open',
  `description` text NOT NULL,
  `creator` varchar(255) NOT NULL,
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assignee` varchar(255) NOT NULL,
  `assigned` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deadline` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `tags` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

--
-- Dumping data for table `issues`
--

INSERT INTO `issues` (`id`, `title`, `status`, `description`, `creator`, `created`, `assignee`, `assigned`, `deadline`, `tags`) VALUES
(1, 'Test issue', 'open', '##Test Issue Details\r\n\r\nThis is the description of the issue. Here are some points:\r\n\r\n* First Point\r\n* Second Point', 'IJMacD@gmail.com', '2017-06-18 04:37:00', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TST'),
(2, 'Another issue', 'closed', 'Short description', 'IJMacD@gmail.com', '2017-06-19 16:43:40', 'IJMacD@gmail.com', '2017-06-19 16:43:40', '2017-06-30 04:40:20', 'TST, English'),
(3, 'Third issue', 'open', 'Short description', 'IJMacD@gmail.com', '2017-06-18 12:57:00', 'iain@i-learner.edu.hk', '2017-06-18 12:57:00', '2017-05-30 02:43:40', 'Wan Chai, English'),
(4, 'This is a new issue', 'closed', 'Is like to add this issue please:\r\n\r\n* Stuff happens and I don''t know why\r\n* Please fix it\r\n', 'ijmacd@gmail.com', '2017-06-07 04:03:23', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', ''),
(5, 'Creating issues is easy', 'closed', 'I want it to be harder =F0=9F=98=A1\r\n', 'ijmacd@gmail.com', '2017-06-07 04:08:38', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', ''),
(6, 'More testing', 'open', 'With more emotions 😀\r\n', 'ijmacd@gmail.com', '2017-06-07 04:13:32', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'Website, '),
(7, 'Time for a new issue', 'closed', 'OK I lied, I tried to add this before. This is a test message.\r\n\r\nIt has some new lines. Some *bold* text.\r\n\r\n* It also has a list\r\n* with <a href="http://www.i-learner.edu.hk">links</a>\r\n', 'iain.ilearner@gmail.com', '2017-06-07 08:40:18', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', ''),
(8, 'Time for a new issue', 'open', 'OK I lied, I tried to add this before. This is a test message.\r\n\r\nIt has some new lines. Some *bold* text.\r\n\r\n* It also has a list\r\n* with <a href="http://www.i-learner.edu.hk">links</a>\r\n', 'iain.ilearner@gmail.com', '2017-06-07 08:41:35', 'iain.ilearner@gmail.com', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'Wan Chai, Admin');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `name`) VALUES
(1, 'IJMacD@gmail.com', 'Iain MacDonald'),
(2, 'iain@i-learner.edu.hk', 'Iain MacDonald'),
(3, 'iain.ilearner@gmail.com', 'Iain MacDonald');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
