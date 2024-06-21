CREATE TABLE IF NOT EXISTS `PlatformOrder` (
    `id` VARCHAR(255),
    `name` VARCHAR(255) NOT NULL, 
    `date` BIGINT NOT NULL,
    `status` INT,
    `paid` DECIMAL(10,2),
    `discount` DECIMAL(10,2),
    `numCommensals` VARCHAR(255),
    `clientId` VARCHAR(255),
    `clientName` VARCHAR(255),
    `clientPhone` VARCHAR(50),
    `storePlatformId` VARCHAR(255),
    `observations` TEXT,
    `address` TEXT,
    `discountCodes` TEXT,
    `typeOrder` INT DEFAULT -1,
    `deliveryPlatform` VARCHAR(255),
    PRIMARY KEY(`id`)
);

CREATE TABLE IF NOT EXISTS `PlatformOrderItem` (
    `id` VARCHAR(255) UNIQUE,
    `platformItemId` VARCHAR(255),
    `parentMenuId` VARCHAR(255),
    `discount` DECIMAL(10,2),
    `discountType` INT,
    `discountCode` VARCHAR(255),
    `orderId` VARCHAR(255),
    `itemName` VARCHAR(255),
    `comment` TEXT,
    `quantity` INT,
    `price` DECIMAL(10,2),
    `total` DECIMAL(10,2),
    `date` BIGINT,
    `weight` DECIMAL(10,2),
    `menuName` VARCHAR(255),
    `variation` VARCHAR(255),
    `vat` DECIMAL(10,2),
    PRIMARY KEY(`id`),
    FOREIGN KEY (`orderId`) REFERENCES `PlatformOrder`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `PlatformComplement` (
    `id` VARCHAR(255) UNIQUE,
    `complementId` VARCHAR(255),
    `platformOrderItemId` VARCHAR(255),
    `complementName` VARCHAR(255),
    `comment` TEXT,
    `quantity` INT,
    `price` DECIMAL(10,2),
    `total` DECIMAL(10,2),
    `date` BIGINT,
    `vat` DECIMAL(10,2),
    PRIMARY KEY(`id`),
    FOREIGN KEY (`platformOrderItemId`) REFERENCES `PlatformOrderItem`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `Config` (
    `shopId` VARCHAR(255) UNIQUE,
    `uberStoreId` VARCHAR(255),
    `globoStoreId` VARCHAR(255),
    `justEatStoreId` VARCHAR(255),
    `authToken` VARCHAR(255),
    PRIMARY KEY(`shopId`)
);