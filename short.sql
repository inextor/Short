
CREATE TABLE `link` (
  `id` int(11) NOT NULL,
  `url` text NOT NULL,
  `clicks` int(11) NOT NULL DEFAULT 0,
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `link`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `link`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
