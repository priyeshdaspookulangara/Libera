-- Libera Database Schema for MySQL
CREATE DATABASE IF NOT EXISTS `Library` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `Library`;

-- ConfigCompany
CREATE TABLE IF NOT EXISTS `ConfigCompany` (
	`CompanyNumber` tinyint NOT NULL PRIMARY KEY,
	`CompanyName` varchar(100) NULL,
	`CompanyStatus` tinyint NULL,
	`CompanyCode` varchar(25) NULL,
	`CompanyRemarks` varchar(250) NULL,
	`CompanyAddress` varchar(100) NULL,
	`CompanyPlace` varchar(50) NULL,
	`CompanyPost` varchar(50) NULL,
	`ComPINCode` varchar(50) NULL,
	`CompanyDistrict` varchar(50) NULL,
	`CompanyPhone` varchar(50) NULL,
	`CompanyEMail` varchar(100) NULL,
	`CompanyWebSite` varchar(100) NULL,
	`ComAccName` varchar(50) NULL,
	`ComAccNumber` varchar(50) NULL,
	`ComBankName` varchar(50) NULL,
	`ComBankBranch` varchar(50) NULL,
	`ComGSTNumber` varchar(50) NULL,
	`ComPANCard` varchar(50) NULL,
	`ComRegNumber` varchar(50) NULL,
	`ComIFSCNumber` varchar(50) NULL,
	`StateNumber` smallint NULL,
	`CompanyLicNo01` varchar(50) NULL,
	`CompanyLicNo02` varchar(50) NULL,
	`LicenseExpiryDate` varchar(50) NULL,
	`CustomerNumber` int NULL,
	`ServerNumber` int NULL,
	`CustomizeNumber` int NULL,
	`CompanyTitle` varchar(100) NULL,
	`ApplicationNumber` int NULL,
	`ImageStreamValue` LONGBLOB NULL,
	`RequestNumber` int NULL,
	`LicenseNumber` int NULL
) ENGINE=InnoDB;

-- ConfigOptionAccounts
CREATE TABLE IF NOT EXISTS `ConfigOptionAccounts` (
	`OptionNumber` int NOT NULL PRIMARY KEY,
	`OptionName` varchar(50) NULL,
	`OptionValue` varchar(250) NULL,
	`OptionType` tinyint NULL,
	`OptionRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- ConfigOptionGeneral
CREATE TABLE IF NOT EXISTS `ConfigOptionGeneral` (
	`OptionNumber` int NOT NULL PRIMARY KEY,
	`OptionName` varchar(50) NULL,
	`OptionValue` varchar(250) NULL,
	`OptionType` tinyint NULL,
	`OptionRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- ConfigPrivilegeItems
CREATE TABLE IF NOT EXISTS `ConfigPrivilegeItems` (
	`PrivilegeNumber` int NOT NULL PRIMARY KEY,
	`PrivilegeName` varchar(50) NULL,
	`PrivilegeStatus` tinyint NULL,
	`PrivilegeNumberParent` int NULL,
	`PrivilegeRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- ConfigPrivilegeValues
CREATE TABLE IF NOT EXISTS `ConfigPrivilegeValues` (
	`PrivilegeNumber` int NOT NULL,
	`UserNumber` smallint NOT NULL,
	`PrivilegeValue` varchar(10) NULL,
	PRIMARY KEY (`UserNumber`, `PrivilegeNumber`)
) ENGINE=InnoDB;

-- ConfigReportItems
CREATE TABLE IF NOT EXISTS `ConfigReportItems` (
	`SettingsNumber` int NOT NULL PRIMARY KEY,
	`SettingsName` varchar(50) NULL,
	`SettingsValue` varchar(25) NULL
) ENGINE=InnoDB;

-- ConfigReportValues
CREATE TABLE IF NOT EXISTS `ConfigReportValues` (
	`SettingsNumber` bigint NOT NULL,
	`ReportNumber` bigint NOT NULL,
	`SettingsValue` varchar(50) NULL,
	PRIMARY KEY (`SettingsNumber`, `ReportNumber`)
) ENGINE=InnoDB;

-- ConfigSoftwareLicense
CREATE TABLE IF NOT EXISTS `ConfigSoftwareLicense` (
	`OptionNumber` int NOT NULL PRIMARY KEY,
	`OptionName` varchar(50) NULL,
	`OptionValue` varchar(250) NULL,
	`OptionType` tinyint NULL,
	`OptionRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- ConfigSoftwareNodes
CREATE TABLE IF NOT EXISTS `ConfigSoftwareNodes` (
	`OptionNumber` int NOT NULL PRIMARY KEY,
	`OptionName` varchar(50) NULL,
	`OptionValue` varchar(250) NULL,
	`OptionType` tinyint NULL,
	`OptionRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- ConfigSoftwareWarning
CREATE TABLE IF NOT EXISTS `ConfigSoftwareWarning` (
	`SettingsNumber` smallint NOT NULL PRIMARY KEY,
	`SettingsName` varchar(50) NULL,
	`SettingsStatus` tinyint NULL,
	`SettingsValue` varchar(2500) NULL,
	`SettingsValue02` varchar(2500) NULL,
	`SettingsStartDate` int NULL,
	`SettingsFrequency` int NULL,
	`SettingsLastShown` int NULL,
	`SettingsGroup` tinyint NULL,
	`SettingsPassword` varchar(50) NULL,
	`SettingsCode` varchar(25) NULL,
	`SettingsRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- ConfigTransNumber
CREATE TABLE IF NOT EXISTS `ConfigTransNumber` (
	`TransSeriesNumber` smallint NOT NULL PRIMARY KEY,
	`TransTypeNumber` smallint NULL,
	`SeriesNumber` tinyint NULL,
	`YearNumber` tinyint NULL,
	`TransactionNumber` int NULL,
	`TransactionDate` date NULL
) ENGINE=InnoDB;

-- ConfigTransType
CREATE TABLE IF NOT EXISTS `ConfigTransType` (
	`TransTypeNumber` smallint NOT NULL PRIMARY KEY,
	`TransTypeName` varchar(50) NULL,
	`TransHeadNumber` bigint NULL,
	`TransTypeGroup` tinyint NULL,
	`TransTypeStatus` tinyint NULL,
	`TransTypeCode` varchar(25) NULL,
	`TransTypeRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- DDCClasses
CREATE TABLE IF NOT EXISTS `DDCClasses` (
	`ClassNumber` int NULL,
	`ClassCode` varchar(50) NULL,
	`ClassTitle` varchar(150) NULL,
	`BaseNumber` int NULL,
	`ClassRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- ExcelBookMaster
CREATE TABLE IF NOT EXISTS `ExcelBookMaster` (
	`BookNumber` bigint NULL,
	`BookName` text NULL,
	`BookRemarks` text NULL,
	`AutherName` text NULL,
	`BookClass` text NULL,
	`BookCode` text NULL,
	`CallNumber` text NULL,
	`BookPrice` double NULL
) ENGINE=InnoDB;

-- ExcelMemberMaster
CREATE TABLE IF NOT EXISTS `ExcelMemberMaster` (
	`MemberNumber` int NOT NULL PRIMARY KEY,
	`MemberName` varchar(150) NULL,
	`MemberType` varchar(50) NULL,
	`MemberAddress` varchar(150) NULL,
	`MemberPlace` varchar(150) NULL,
	`MemberPost` varchar(150) NULL,
	`MemberPhone` varchar(50) NULL,
	`MemberAge` tinyint NULL,
	`MemberSex` tinyint NULL,
	`MemBirthDate` date NULL,
	`MemberRemarks` varchar(250) NULL,
	`MemberTypeNumber` tinyint NULL,
	`MemberStatus` tinyint NULL
) ENGINE=InnoDB;

-- ImageMaster
CREATE TABLE IF NOT EXISTS `ImageMaster` (
	`MasterTypeNumber` int NOT NULL,
	`MasterKeyNumber` bigint NOT NULL,
	`ImageStreamValue` LONGBLOB NULL,
	PRIMARY KEY (`MasterTypeNumber`, `MasterKeyNumber`)
) ENGINE=InnoDB;

-- MstrAreaCodes
CREATE TABLE IF NOT EXISTS `MstrAreaCodes` (
	`AreaCodeNumber` int NOT NULL PRIMARY KEY,
	`AreaCodeName` varchar(50) NULL,
	`AreaCodeStatus` tinyint NULL
) ENGINE=InnoDB;

-- MstrAuthor
CREATE TABLE IF NOT EXISTS `MstrAuthor` (
	`AuthorNumber` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`AuthorName` varchar(100) NULL,
	`AuthorStatus` tinyint NULL DEFAULT 1,
	`AuthorCode` varchar(50) NULL,
	`AuthorRemarks` varchar(150) NULL,
	`AuthorPlace` varchar(50) NULL,
	`AuthorBirthDate` datetime NULL,
	`LanguageNumber` tinyint NULL
) ENGINE=InnoDB;

-- MstrAuthorAlias
CREATE TABLE IF NOT EXISTS `MstrAuthorAlias` (
	`AuthorNumber` int NOT NULL,
	`AuthorPenName` varchar(50) NULL
) ENGINE=InnoDB;

-- MstrAuthorType
CREATE TABLE IF NOT EXISTS `MstrAuthorType` (
	`AuthorTypeNumber` tinyint NOT NULL PRIMARY KEY,
	`AuthorTypeName` varchar(50) NULL,
	`AuthorTypeStatus` tinyint NULL
) ENGINE=InnoDB;

-- MstrBookKeywords
CREATE TABLE IF NOT EXISTS `MstrBookKeywords` (
	`BookNumber` int NOT NULL,
	`BookKeywords` varchar(150) NULL
) ENGINE=InnoDB;

-- MstrBookStock
CREATE TABLE IF NOT EXISTS `MstrBookStock` (
	`BookNumber` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`BookName` varchar(150) NULL,
	`BookStatus` tinyint NULL DEFAULT 1, -- 1=Available, 2=Issued, 0=Inactive
	`BookCode` varchar(50) NULL,
	`BookRemarks` varchar(150) NULL,
	`CallNumber` varchar(50) NULL,
	`ISBNumber` varchar(50) NULL,
	`AccessionNumber` varchar(50) NULL,
	`AuthorNumber` int NULL,
	`ClassNumber` int NULL,
	`PublisherNumber` int NULL,
	`InvoiceNumber` varchar(50) NULL,
	`InvoiceDate` datetime NULL,
	`BookPrice` double NULL,
	`LanguageNumber` tinyint NULL,
	`CategoryNumber` tinyint NULL
) ENGINE=InnoDB;

-- MstrBookType
CREATE TABLE IF NOT EXISTS `MstrBookType` (
	`BookTypeNumber` tinyint NOT NULL PRIMARY KEY,
	`BookTypeName` varchar(50) NULL,
	`BookTypeStatus` tinyint NULL
) ENGINE=InnoDB;

-- MstrCategory
CREATE TABLE IF NOT EXISTS `MstrCategory` (
	`CategoryNumber` tinyint NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`CategoryName` varchar(50) NULL,
	`CategoryStatus` tinyint NULL DEFAULT 1
) ENGINE=InnoDB;

-- MstrClassification
CREATE TABLE IF NOT EXISTS `MstrClassification` (
	`ClassNumber` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`ClassName` varchar(150) NULL,
	`ClassStatus` tinyint NULL DEFAULT 1,
	`ClassCode` varchar(50) NULL,
	`ClassRemarks` varchar(250) NULL,
	`ClassNumberParent` int NULL
) ENGINE=InnoDB;

-- MstrCostCentres
CREATE TABLE IF NOT EXISTS `MstrCostCentres` (
	`CostCentreNumber` int NOT NULL PRIMARY KEY,
	`CostCentreName` varchar(50) NULL,
	`CostCentreStatus` tinyint NULL,
	`CostCentreRemarks` varchar(50) NULL,
	`LedgerNumberParent` int NULL,
	`PriorityNumber` int NULL,
	`CostCentreCode` varchar(25) NULL,
	`CostGroupNumber` smallint NULL
) ENGINE=InnoDB;

-- MstrCostGroup
CREATE TABLE IF NOT EXISTS `MstrCostGroup` (
	`CostGroupNumber` smallint NOT NULL PRIMARY KEY,
	`CostGroupName` varchar(50) NULL,
	`CostGroupStatus` tinyint NULL
) ENGINE=InnoDB;

-- MstrDistributor
CREATE TABLE IF NOT EXISTS `MstrDistributor` (
	`DistributorNumber` smallint NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`DistributorName` varchar(100) NOT NULL,
	`DistributorStatus` tinyint NULL DEFAULT 1,
	`DistributorAddress` varchar(150) NULL,
	`DistributorPlace` varchar(50) NULL,
	`DistributorPhone` varchar(50) NULL
) ENGINE=InnoDB;

-- MstrDocuments
CREATE TABLE IF NOT EXISTS `MstrDocuments` (
	`DocumentNumber` int NOT NULL PRIMARY KEY,
	`DocumentName` varchar(50) NULL,
	`DocumentStatus` tinyint NULL
) ENGINE=InnoDB;

-- MstrFiscalYear
CREATE TABLE IF NOT EXISTS `MstrFiscalYear` (
	`YearNumber` tinyint NOT NULL PRIMARY KEY,
	`YearDescription` varchar(50) NULL,
	`YearStartDate` date NULL,
	`YearStopDate` date NULL,
	`CurrentYear` tinyint NULL DEFAULT 1,
	`YearLocked` tinyint NULL DEFAULT 0,
	`YearRemarks` varchar(150) NULL,
	`OpeningStock` date NULL,
	`YearShortName` varchar(25) NULL
) ENGINE=InnoDB;

-- MstrIssueTypes
CREATE TABLE IF NOT EXISTS `MstrIssueTypes` (
	`IssueTypeNumber` int NOT NULL PRIMARY KEY,
	`IssueTypeName` varchar(50) NULL,
	`IssueTypeStatus` tinyint NULL,
	`IssueTypeCode` varchar(50) NULL,
	`IssueTypeRemarks` varbinary(50) NULL,
	`AllowedDays` int NULL
) ENGINE=InnoDB;

-- MstrLanguage
CREATE TABLE IF NOT EXISTS `MstrLanguage` (
	`LanguageNumber` tinyint NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`LanguageName` varchar(50) NULL,
	`LanguageStatus` tinyint NULL DEFAULT 1
) ENGINE=InnoDB;

-- MstrLedgerGroup
CREATE TABLE IF NOT EXISTS `MstrLedgerGroup` (
	`LedgerGroupNumber` smallint NOT NULL PRIMARY KEY,
	`LedgerGroupCode` varchar(25) NULL,
	`LedgerGroupName` varchar(100) NOT NULL,
	`LedgerGroupStatus` tinyint NULL DEFAULT 1,
	`LedgerGroupRemarks` varchar(150) NULL,
	`LedgerGroupNumberParent` smallint NULL,
	`CreatedDate` datetime NULL,
	`UserNumberCreated` smallint NULL,
	`LedgerSortOrder` smallint NULL,
	`LedgerNameDuplication` tinyint NULL,
	`LedgerNumberJournal` int NULL
) ENGINE=InnoDB;

-- MstrLedger
CREATE TABLE IF NOT EXISTS `MstrLedger` (
	`LedgerNumber` int NOT NULL PRIMARY KEY,
	`LedgerCode` varchar(50) NULL,
	`LedgerName` varchar(100) NULL,
	`LedgerStatus` tinyint NULL DEFAULT 1,
	`LedgerGroupNumber` smallint NULL,
	`LedgerRemarks` varchar(150) NULL,
	`LedgerSortOrder` int NULL
) ENGINE=InnoDB;

-- MstrLedgerDetails
CREATE TABLE IF NOT EXISTS `MstrLedgerDetails` (
	`LedgerNumber` int NOT NULL PRIMARY KEY,
	`HouseName` varchar(50) NULL,
	`LedgerPlace` varchar(50) NULL,
	`LedgerPost` varchar(50) NULL,
	`PostalCode` varchar(50) NULL,
	`PhoneNumber` varchar(50) NULL,
	`GSTNumber` varchar(50) NULL,
	`StateNumber` tinyint NULL,
	`PriceTypeNumber` tinyint NULL,
	`AreaCodeNumber` smallint NULL,
	`LedgerCreditDays` smallint NULL,
	`LedgerCreditLimit` double NULL,
	`DailyWagesAmount` double NULL,
	`LedgerNumberAgent` int NULL,
	`PANCardNumber` varchar(50) NULL,
	`BankNameLedger` varchar(50) NULL,
	`BankAccNumber` varchar(50) NULL,
	`BankBranchName` varchar(50) NULL,
	`BankIFSCNumber` varchar(50) NULL,
	`VoucherMatching` tinyint NULL,
	`LedgerSortOrder` int NULL,
	`LedgerEmail` varchar(150) NULL,
	`ContactPerson` varchar(150) NULL,
	`ChequeNextNumber` bigint NULL,
	`ChequeDesignName` varchar(150) NULL,
	`LedgerDistance` varchar(50) NULL,
	`LedgerPrintName` varchar(150) NULL,
	`SeriesNumber` smallint NULL,
	`CostGroupNumber` smallint NULL,
	`LedgerNumberSalesman` int NULL
) ENGINE=InnoDB;

-- MstrMember
CREATE TABLE IF NOT EXISTS `MstrMember` (
	`MemberNumber` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`MemberName` text NULL,
	`PhoneHome` bigint NULL,
	`HouseName` text NULL,
	`StudentSex` text NULL,
	`MemBirthDate` datetime NULL,
	`MemberRemarks` text NULL
) ENGINE=InnoDB;

-- MstrMemberClass
CREATE TABLE IF NOT EXISTS `MstrMemberClass` (
	`MemberClassNumber` tinyint NOT NULL PRIMARY KEY,
	`MemberClassName` varchar(50) NULL,
	`MemberClassStatus` tinyint NULL,
	`MembershipAmount` double NULL,
	`SubscriptionAmount` double NULL,
	`MemberClassRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- MstrMemberDetails
CREATE TABLE IF NOT EXISTS `MstrMemberDetails` (
	`MemberNumber` int NOT NULL PRIMARY KEY,
	`GuardianName` varchar(50) NULL,
	`Relationship` tinyint NULL,
	`BloodGroup` tinyint NULL,
	`RegistrationFees` double NULL,
	`ConsultationFees` double NULL,
	`MaritalStatus` tinyint NULL,
	`EmployeeNumber` int NULL,
	`MemberNumberAdvice` int NULL,
	`MemberJoinDate` date NULL,
	`ApprovedDate` date NULL,
	`NomineeName` varchar(50) NULL,
	`RelationNumberNominee` tinyint NULL,
	`NomineeDocument` varchar(50) NULL,
	`DocTypeNumberNominee` tinyint NULL
) ENGINE=InnoDB;

-- MstrMemberFamily
CREATE TABLE IF NOT EXISTS `MstrMemberFamily` (
	`MemberNumber` int NOT NULL,
	`SerialNumber` tinyint NOT NULL,
	`MemberName` varchar(50) NULL,
	`MemberGender` tinyint NULL,
	`MemberAge` tinyint NULL,
	`RelationNumber` int NULL,
	`MaritalStatus` tinyint NULL,
	`MemberRemarks` varchar(150) NULL,
	PRIMARY KEY (`MemberNumber`, `SerialNumber`)
) ENGINE=InnoDB;

-- MstrMemberType
CREATE TABLE IF NOT EXISTS `MstrMemberType` (
	`MemberTypeNumber` tinyint NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`MemberTypeName` varchar(50) NULL,
	`MemberTypeStatus` tinyint NULL DEFAULT 1,
	`MemberTypeRemarks` varchar(50) NULL,
	`MemberTypeCode` varchar(25) NULL,
	`ApplicationFee` double NULL DEFAULT 0,
	`MembershipAmount` double NULL DEFAULT 0,
	`SubscriptionAmount` double NULL DEFAULT 0,
	`WorkingFund` double NULL DEFAULT 0,
	`DepositAmount` double NULL DEFAULT 0,
	`OtherCharges` double NULL DEFAULT 0,
	`MemberCodeNext` int NULL,
	`MemberCodePrefix` varchar(50) NULL,
	`IssueBookCount` tinyint NULL DEFAULT 3,
	`IssueAllowedDays` tinyint NULL DEFAULT 14,
	`IssueFineAmount` double NULL DEFAULT 1.0,
	`IssueFineDays` int NULL DEFAULT 1,
	`IssueRemarks` varchar(50) NULL
) ENGINE=InnoDB;

-- MstrPublisher
CREATE TABLE IF NOT EXISTS `MstrPublisher` (
	`PublisherNumber` smallint NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`PublisherName` varchar(100) NOT NULL,
	`PublisherStatus` tinyint NULL DEFAULT 1,
	`PublisherCode` varchar(25) NULL,
	`PublisherRemarks` varchar(250) NULL,
	`PublisherAddress` varchar(100) NULL,
	`PublisherPlace` varchar(100) NULL,
	`PublisherPhone` varchar(50) NULL
) ENGINE=InnoDB;

-- MstrRelation
CREATE TABLE IF NOT EXISTS `MstrRelation` (
	`RelationNumber` int NOT NULL PRIMARY KEY,
	`RelationName` varchar(50) NULL,
	`RelationStatus` tinyint NULL
) ENGINE=InnoDB;

-- MstrSeries
CREATE TABLE IF NOT EXISTS `MstrSeries` (
	`SeriesNumber` smallint NOT NULL PRIMARY KEY,
	`SeriesName` varchar(50) NULL,
	`SeriesStatus` tinyint NULL,
	`SeriesCode` varchar(25) NULL,
	`SeriesRemarks` varchar(150) NULL,
	`SeriesTypeNumber` tinyint NULL
) ENGINE=InnoDB;

-- MstrStateCode
CREATE TABLE IF NOT EXISTS `MstrStateCode` (
	`StateNumber` tinyint NOT NULL PRIMARY KEY,
	`StateName` varchar(50) NULL,
	`StateStatus` tinyint NULL,
	`StateCode` varchar(10) NULL,
	`StateRemarks` varchar(150) NULL,
	`StateOrder` tinyint NULL,
	`StateType` varchar(10) NULL
) ENGINE=InnoDB;

-- MstrUserLevels
CREATE TABLE IF NOT EXISTS `MstrUserLevels` (
	`UserLevelNumber` tinyint NOT NULL PRIMARY KEY,
	`UserLevelName` varchar(50) NULL,
	`UserLevelStatus` tinyint NULL
) ENGINE=InnoDB;

-- MstrUsers
CREATE TABLE IF NOT EXISTS `MstrUsers` (
	`UserNumber` smallint NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`UserLevelNumber` tinyint NULL,
	`UserName` varchar(50) NULL,
	`UserPassword` varchar(255) NULL,
	`UserStatus` tinyint NULL DEFAULT 1,
	`UserRemarks` varchar(150) NULL,
	`UserCode` varchar(50) NULL
) ENGINE=InnoDB;

-- TransAccMatching
CREATE TABLE IF NOT EXISTS `TransAccMatching` (
	`TransTypeNumberMain` smallint NULL,
	`TransHeadNumberMain` int NULL,
	`TransTypeNumberMatch` smallint NULL,
	`TransHeadNumberMatch` int NULL,
	`YearNumberOpening` tinyint NULL,
	`TransactionAmount` double NULL
) ENGINE=InnoDB;

-- TransBookIssue
CREATE TABLE IF NOT EXISTS `TransBookIssue` (
	`IssueID` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`SlNo` double NULL,
	`IssueDate` datetime NULL,
	`DueDate` datetime NULL,
	`ReturnDate` datetime NULL,
	`BookNumber` varchar(255) NULL,
	`AccessionNumber` varchar(50) NULL,
	`MemberNumber` double NULL,
	`MemberName` text NULL,
	`IssueStatus` tinyint NULL DEFAULT 1, -- 1=Issued, 2=Returned
	`FineAmount` double NULL DEFAULT 0,
	`FineWaived` double NULL DEFAULT 0,
	`FineCollected` double NULL DEFAULT 0,
	`Remarks` varchar(255) NULL
) ENGINE=InnoDB;

-- TransLedgerHead
CREATE TABLE IF NOT EXISTS `TransLedgerHead` (
	`TransHeadNumber` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`TransactionDate` date NULL,
	`TransTypeNumber` smallint NULL,
	`SeriesNumber` tinyint NULL,
	`YearNumber` tinyint NULL,
	`TransactionNumber` int NULL,
	`TransactionRemarks` varchar(150) NULL,
	`CostCentreNumber` int NULL,
	`LocationNumber` tinyint NULL,
	`ServerNumber` int NULL,
	`UserNumber` smallint NULL,
	`CounterName` varchar(25) NULL,
	`ReferranceNumber` int NULL,
	`CompanyNumber` tinyint NULL,
	`AreaCodeNumber` smallint NULL,
	`LedgerNumberGroup` int NULL
) ENGINE=InnoDB;

-- TransLedgerDetails
CREATE TABLE IF NOT EXISTS `TransLedgerDetails` (
	`TransHeadNumber` int NOT NULL,
	`TransSerialNumber` smallint NOT NULL,
	`ReconcileDate` date NULL,
	`LedgerNumberBook` int NULL,
	`LedgerNumberHead` int NULL,
	`TransNarration` varchar(150) NULL,
	`ReceiptAmount` double NULL,
	`PaymentAmount` double NULL,
	`CustomerNumber` int NULL,
	`MemberNumber` int NULL,
	PRIMARY KEY (`TransHeadNumber`, `TransSerialNumber`)
) ENGINE=InnoDB;

-- TransLedgerMain
CREATE TABLE IF NOT EXISTS `TransLedgerMain` (
	`TransHeadNumber` int NOT NULL PRIMARY KEY,
	`TransactionDate` date NULL,
	`TransTypeNumber` smallint NULL,
	`SeriesNumber` tinyint NULL,
	`YearNumber` tinyint NULL,
	`TransactionNumber` int NULL,
	`ReconcileDate` date NULL,
	`CustomerName` varchar(50) NULL,
	`LedgerNumberBook` int NULL,
	`LedgerNumberHead` int NULL,
	`CostCentreNumber` int NULL,
	`MemberNumber` int NULL,
	`TransactionAmount` double NULL,
	`TransactionRemarks` varchar(150) NULL,
	`CustomerNumber` int NULL,
	`ReferranceNumber` int NULL,
	`CompanyNumber` tinyint NULL,
	`ChequeNumber` varchar(50) NULL,
	`ChequeDate` date NULL,
	`ChequeIssued` date NULL,
	`LocationNumber` tinyint NULL,
	`ServerNumber` int NULL,
	`UserNumber` smallint NULL,
	`CounterName` varchar(50) NULL
) ENGINE=InnoDB;

-- TransLedgerOpening
CREATE TABLE IF NOT EXISTS `TransLedgerOpening` (
	`CompanyNumber` tinyint NULL,
	`YearNumber` tinyint NULL,
	`LedgerNumber` int NULL,
	`DebitAmount` double NULL,
	`CreditAmount` double NULL,
	`LocationNumber` tinyint NULL
) ENGINE=InnoDB;

-- TransMemberCharges
CREATE TABLE IF NOT EXISTS `TransMemberCharges` (
	`TransHeadNumber` int NOT NULL PRIMARY KEY,
	`TransTypeNumber` smallint NULL,
	`TransactionDate` date NULL,
	`SeriesNumber` tinyint NULL,
	`YearNumber` tinyint NULL,
	`TransactionNumber` int NULL,
	`CustomerName` varchar(50) NULL,
	`TransactionAmount` double NULL,
	`TransactionRemarks` varchar(150) NULL,
	`LedgerNumberBook` int NULL,
	`MemberNumber` int NULL,
	`SubscriptionStart` date NULL,
	`SubscriptionStop` date NULL
) ENGINE=InnoDB;

-- TransMemberOpening
CREATE TABLE IF NOT EXISTS `TransMemberOpening` (
	`MemberNumber` int NULL,
	`LedgerNumber` int NULL,
	`YearNumber` tinyint NULL,
	`DebitAmount` double NULL,
	`CreditAmount` double NULL
) ENGINE=InnoDB;

-- TransMemberPending
CREATE TABLE IF NOT EXISTS `TransMemberPending` (
	`TransHeadNumber` int NOT NULL,
	`TransTypeNumber` smallint NOT NULL,
	`TransactionDate` date NULL,
	`MemberNumber` int NULL,
	`TransactionAmount` double NULL,
	PRIMARY KEY (`TransHeadNumber`, `TransTypeNumber`)
) ENGINE=InnoDB;

-- AccTransaction
CREATE TABLE IF NOT EXISTS `AccTransaction` (
	`AccTransNumber` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`TransHeadNumber` int NOT NULL,
	`TransTypeNumber` smallint NOT NULL,
	`CompanyNumber` tinyint NULL,
	`TransactionDate` date NULL,
	`ReconcileDate` date NULL,
	`LedgerNumberBook` int NULL,
	`LedgerNumberHead` int NULL,
	`CostCentreNumber` int NULL,
	`MemberNumber` int NULL,
	`TransactionAmount` double NULL,
	`TransactionRemarks` varchar(150) NULL,
	`CostCentreNumberBook` int NULL,
	`CostCentreNumberHead` int NULL,
	`LocationNumber` tinyint NULL
) ENGINE=InnoDB;

-- AccTransDetails
CREATE TABLE IF NOT EXISTS `AccTransDetails` (
	`AccTransNumber` bigint NULL,
	`TransHeadNumber` int NULL,
	`TransTypeNumber` smallint NULL,
	`TransactionDate` date NULL,
	`ReconcileDate` date NULL,
	`CostCentreNumber` int NULL,
	`MemberNumber` int NULL,
	`CustomerNumber` int NULL,
	`LedgerDebitAmount` double NULL,
	`LedgerCreditAmount` double NULL,
	`TransactionRemarks` varchar(150) NULL
) ENGINE=InnoDB;

-- Seed Data Insertion --

INSERT INTO `ConfigCompany` (`CompanyNumber`, `CompanyName`, `CompanyStatus`, `CompanyCode`, `CompanyRemarks`, `CompanyAddress`, `CompanyPlace`, `CompanyPost`, `ComPINCode`, `CompanyDistrict`, `CompanyPhone`, `CompanyEMail`, `CompanyWebSite`, `ComAccName`, `ComAccNumber`, `ComBankName`, `ComBankBranch`, `ComGSTNumber`, `ComPANCard`, `ComRegNumber`, `ComIFSCNumber`, `StateNumber`, `CompanyLicNo01`, `CompanyLicNo02`, `LicenseExpiryDate`, `CustomerNumber`, `ServerNumber`, `CustomizeNumber`, `CompanyTitle`, `ApplicationNumber`, `ImageStreamValue`, `RequestNumber`, `LicenseNumber`) VALUES
(1, 'KASTUBA SMARAKA VAYANASALA', 0, '111901250201', '', 'EAST NADA', 'ELAMKUNNAPUZHA', 'ELAMKUNNAPUZHA', '682503', 'ERNAKULAM', '9048123253', 'kgpradeep753@gmail.com', 'nil', '', '', '', '', '', '', '3087', '', 18, '', '', '-  -', 111593, NULL, 0, '', 111901, NULL, 10521, 0)
ON DUPLICATE KEY UPDATE `CompanyName` = VALUES(`CompanyName`);

INSERT INTO `MstrUserLevels` (`UserLevelNumber`, `UserLevelName`, `UserLevelStatus`) VALUES
(1, 'Administrator', 1),
(2, 'Librarian', 1)
ON DUPLICATE KEY UPDATE `UserLevelName` = VALUES(`UserLevelName`);

INSERT INTO `MstrUsers` (`UserNumber`, `UserLevelNumber`, `UserName`, `UserPassword`, `UserStatus`, `UserRemarks`, `UserCode`) VALUES
(1, 1, 'admin', 'admin123', 1, 'System Administrator', 'ADM001')
ON DUPLICATE KEY UPDATE `UserName` = VALUES(`UserName`);

INSERT INTO `MstrFiscalYear` (`YearNumber`, `YearDescription`, `YearStartDate`, `YearStopDate`, `CurrentYear`, `YearLocked`, `YearRemarks`, `OpeningStock`, `YearShortName`) VALUES
(25, '2024-2025', '2024-04-01', '2025-03-31', 1, 0, 'Active Financial Year', '2024-04-01', '2024-25')
ON DUPLICATE KEY UPDATE `YearDescription` = VALUES(`YearDescription`);

-- Ledger Groups Structure
INSERT INTO `MstrLedgerGroup` (`LedgerGroupNumber`, `LedgerGroupCode`, `LedgerGroupName`, `LedgerGroupStatus`, `LedgerGroupRemarks`, `LedgerGroupNumberParent`) VALUES
(1, 'AST', 'Assets', 1, 'Primary Group Assets', NULL),
(2, 'LIB', 'Liabilities', 1, 'Primary Group Liabilities', NULL),
(3, 'INC', 'Income', 1, 'Primary Group Income', NULL),
(4, 'EXP', 'Expenses', 1, 'Primary Group Expenses', NULL),
(5, 'EQU', 'Capital / Equity', 1, 'Primary Group Capital', NULL),
(10, 'CSH', 'Cash-in-hand', 1, 'Cash Accounts', 1),
(11, 'BNK', 'Bank Accounts', 1, 'Bank Accounts', 1),
(12, 'DRS', 'Sundry Debtors', 1, 'Member Receivables', 1),
(13, 'DIR_INC', 'Direct Income', 1, 'Library Fees & Fines', 3),
(14, 'CRS', 'Sundry Creditors', 1, 'Distributor Payables', 2),
(15, 'IND_EXP', 'Indirect Expenses', 1, 'Operating Expenses', 4)
ON DUPLICATE KEY UPDATE `LedgerGroupName` = VALUES(`LedgerGroupName`);

-- Essential Ledgers
INSERT INTO `MstrLedger` (`LedgerNumber`, `LedgerCode`, `LedgerName`, `LedgerStatus`, `LedgerGroupNumber`, `LedgerRemarks`, `LedgerSortOrder`) VALUES
(1, 'CSH01', 'Cash Account', 1, 10, 'Default Cash Account', 1),
(10012, 'FIN01', 'Book Fine Income', 1, 13, 'Income from book late returns', 2),
(10013, 'SUB01', 'Subscription Fee Income', 1, 13, 'Income from member subscriptions', 3),
(10014, 'REG01', 'Registration Fee Income', 1, 13, 'Income from new member registrations', 4),
(10015, 'DEP01', 'Membership Deposit', 1, 2, 'Liability for member caution deposits', 5),
(10016, 'APP01', 'Application Fee Income', 1, 13, 'Income from application processing', 6),
(10017, 'PUR01', 'Book Purchase Account', 1, 15, 'Expense on acquiring library books', 7),
(10018, 'GEN01', 'General Income', 1, 13, 'Miscellaneous revenue', 8),
(10022, 'BNK01', 'Bank Account', 1, 11, 'Main Operating Bank Account', 9),
(10023, 'MEM01', 'Member Ledger', 1, 12, 'Aggregate Member Receivables', 10),
(10029, 'MISC_INC', 'Miscellaneous Fee Income', 1, 13, 'Other income', 11),
(10044, 'ALW01', 'Staff Allowance Expense', 1, 15, 'Staff and librarian allowances', 12),
(10056, 'REF01', 'Refreshment & General Expense', 1, 15, 'General office operating expenses', 13),
(602, 'EXP_MISC', 'Miscellaneous Expense', 1, 15, 'Other operational costs', 14)
ON DUPLICATE KEY UPDATE `LedgerName` = VALUES(`LedgerName`);

-- Config Trans Types
INSERT INTO `ConfigTransType` (`TransTypeNumber`, `TransTypeName`, `TransHeadNumber`, `TransTypeGroup`, `TransTypeStatus`, `TransTypeCode`, `TransTypeRemarks`) VALUES
(11, 'Cash Receipt / Payment', 1, 1, 1, 'CP', 'General Cash Transactions'),
(12, 'Payment Voucher', 2, 1, 1, 'PV', 'Payment Vouchers'),
(13, 'Journal Voucher', 3, 1, 1, 'JV', 'Journal Postings'),
(14, 'Member Receipt', 4, 1, 1, 'MR', 'Receipt from Members for Fees & Fines'),
(20, 'Book Purchase / Issue Transaction', 5, 1, 1, 'BP', 'Book Purchase Ledger Posting')
ON DUPLICATE KEY UPDATE `TransTypeName` = VALUES(`TransTypeName`);

-- Member Types Rules
INSERT INTO `MstrMemberType` (`MemberTypeNumber`, `MemberTypeName`, `MemberTypeStatus`, `MemberTypeCode`, `ApplicationFee`, `MembershipAmount`, `SubscriptionAmount`, `WorkingFund`, `DepositAmount`, `OtherCharges`, `IssueBookCount`, `IssueAllowedDays`, `IssueFineAmount`, `IssueFineDays`) VALUES
(1, 'Standard Member', 1, 'STD', 10.0, 100.0, 50.0, 0.0, 200.0, 0.0, 3, 14, 2.0, 1),
(2, 'Student Member', 1, 'STU', 5.0, 50.0, 25.0, 0.0, 100.0, 0.0, 2, 10, 1.0, 1),
(3, 'Senior / Premium Member', 1, 'SR', 15.0, 150.0, 75.0, 0.0, 300.0, 0.0, 5, 21, 1.0, 1)
ON DUPLICATE KEY UPDATE `MemberTypeName` = VALUES(`MemberTypeName`);

-- Seed DDC Classes
INSERT INTO `DDCClasses` (`ClassNumber`, `ClassCode`, `ClassTitle`, `BaseNumber`, `ClassRemarks`) VALUES
(1, '1', 'Classification', 0, NULL),
(2, '000', 'Generalities', 1, NULL),
(3, '100', 'Philosophy & Psychology', 1, NULL),
(4, '200', 'Religion', 1, NULL),
(5, '300', 'Social Sciences', 1, NULL),
(6, '400', 'Language', 1, NULL),
(7, '500', 'Science', 1, NULL),
(8, '600', 'Technology', 1, NULL),
(9, '700', 'Arts & Recreation', 1, NULL),
(10, '800', 'Literature', 1, NULL),
(11, '900', 'History & Geography', 1, NULL)
ON DUPLICATE KEY UPDATE `ClassTitle` = VALUES(`ClassTitle`);

-- Seed Master Classifications
INSERT INTO `MstrClassification` (`ClassNumber`, `ClassName`, `ClassStatus`, `ClassCode`, `ClassRemarks`, `ClassNumberParent`) VALUES
(1, 'Generalities', 1, '000', 'Generalities', NULL),
(2, 'Social Sciences', 1, '300', 'Social Sciences', NULL),
(3, 'Science & Technology', 1, '500', 'Science & Technology', NULL),
(4, 'Literature', 1, '800', 'Literature & Fiction', NULL),
(5, 'History & Biography', 1, '900', 'History & Geography', NULL)
ON DUPLICATE KEY UPDATE `ClassName` = VALUES(`ClassName`);

-- Seed Authors
INSERT INTO `MstrAuthor` (`AuthorNumber`, `AuthorName`, `AuthorStatus`, `AuthorCode`) VALUES
(1, 'William Shakespeare', 1, 'AUT001'),
(2, 'Arthur Conan Doyle', 1, 'AUT002'),
(3, 'Agatha Christie', 1, 'AUT003'),
(4, 'Leo Tolstoy', 1, 'AUT004'),
(5, 'J.K. Rowling', 1, 'AUT005')
ON DUPLICATE KEY UPDATE `AuthorName` = VALUES(`AuthorName`);

-- Seed Categories
INSERT INTO `MstrCategory` (`CategoryNumber`, `CategoryName`, `CategoryStatus`) VALUES
(1, 'Fiction', 1),
(2, 'Non-Fiction', 1),
(3, 'Reference', 1),
(4, 'Periodical', 1)
ON DUPLICATE KEY UPDATE `CategoryName` = VALUES(`CategoryName`);

-- Seed Languages
INSERT INTO `MstrLanguage` (`LanguageNumber`, `LanguageName`, `LanguageStatus`) VALUES
(1, 'English', 1),
(2, 'Malayalam', 1),
(3, 'Hindi', 1)
ON DUPLICATE KEY UPDATE `LanguageName` = VALUES(`LanguageName`);

-- Seed Publishers
INSERT INTO `MstrPublisher` (`PublisherNumber`, `PublisherName`, `PublisherStatus`, `PublisherCode`) VALUES
(1, 'Penguin Random House', 1, 'PUB001'),
(2, 'HarperCollins', 1, 'PUB002'),
(3, 'Oxford University Press', 1, 'PUB003')
ON DUPLICATE KEY UPDATE `PublisherName` = VALUES(`PublisherName`);

-- Seed Books
INSERT INTO `MstrBookStock` (`BookNumber`, `BookName`, `BookStatus`, `BookCode`, `CallNumber`, `ISBNumber`, `AccessionNumber`, `AuthorNumber`, `ClassNumber`, `PublisherNumber`, `BookPrice`, `LanguageNumber`, `CategoryNumber`) VALUES
(1, 'Hamlet', 1, 'B001', 'A0001', '978-0140714159', 'ACC1001', 1, 4, 1, 250.00, 1, 1),
(2, 'The Adventures of Sherlock Holmes', 1, 'B002', 'A0002', '978-0141034348', 'ACC1002', 2, 4, 1, 300.00, 1, 1),
(3, 'Murder on the Orient Express', 1, 'B003', 'A0003', '978-0007119318', 'ACC1003', 3, 4, 2, 350.00, 1, 1),
(4, 'War and Peace', 1, 'B004', 'A0004', '978-0140447934', 'ACC1004', 4, 4, 1, 500.00, 1, 1),
(5, 'Harry Potter and the Sorcerer\'s Stone', 1, 'B005', 'A0005', '978-0590353427', 'ACC1005', 5, 4, 2, 450.00, 1, 1)
ON DUPLICATE KEY UPDATE `BookName` = VALUES(`BookName`);

-- Seed Sample Members
INSERT INTO `MstrMember` (`MemberNumber`, `MemberName`, `PhoneHome`, `HouseName`, `StudentSex`, `MemBirthDate`, `MemberRemarks`) VALUES
(1, 'John Doe', 9876543210, 'Rose Cottage', 'Male', '1995-05-15', 'Active Member'),
(2, 'Jane Smith', 9876543211, 'Green Villa', 'Female', '2000-08-20', 'Student Member')
ON DUPLICATE KEY UPDATE `MemberName` = VALUES(`MemberName`);

INSERT INTO `MstrMemberDetails` (`MemberNumber`, `GuardianName`, `RegistrationFees`, `MemberJoinDate`, `ApprovedDate`) VALUES
(1, 'Robert Doe', 100.0, '2024-01-10', '2024-01-10'),
(2, 'David Smith', 50.0, '2024-02-01', '2024-02-01')
ON DUPLICATE KEY UPDATE `GuardianName` = VALUES(`GuardianName`);

-- Seed AccTransactions from prompt sample
INSERT INTO `AccTransaction` (`AccTransNumber`, `TransHeadNumber`, `TransTypeNumber`, `CompanyNumber`, `TransactionDate`, `ReconcileDate`, `LedgerNumberBook`, `LedgerNumberHead`, `CostCentreNumber`, `MemberNumber`, `TransactionAmount`, `TransactionRemarks`, `CostCentreNumberBook`, `CostCentreNumberHead`, `LocationNumber`) VALUES
(92, 1, 14, 1, '2024-04-05', '2024-04-05', 1, 10012, NULL, 1, 5, 'RT.NO.7763', 0, 0, 0),
(93, 1, 14, 1, '2024-04-05', '2024-04-05', 1, 10015, NULL, 1, 3, '', 0, 0, 0),
(94, 1, 14, 1, '2024-04-05', '2024-04-05', 1, 10016, NULL, 1, 2, '', 0, 0, 0),
(95, 1, 14, 1, '2024-04-05', '2024-04-05', 1, 10014, NULL, 1, 25, '', 0, 0, 0),
(96, 1, 14, 1, '2024-04-05', '2024-04-05', 1, 602, NULL, 1, 15, '', 0, 0, 0),
(97, 2, 14, 1, '2024-04-16', '2024-04-16', 1, 10013, NULL, 1, 100, 'RT.NO.7764', 0, 0, 0),
(98, 3, 14, 1, '2024-04-18', '2024-04-18', 1, 10023, NULL, 1, 12360, '', 0, 0, 0),
(99, 3, 14, 1, '2024-04-18', '2024-04-18', 10022, 1, NULL, 1, 12360, '', 0, 0, 0)
ON DUPLICATE KEY UPDATE `TransactionAmount` = VALUES(`TransactionAmount`);
