-- ONE OFFICE – starting content. install.php runs this once on a fresh install.
-- Contact details are placeholders: change them in Admin → Site settings.

INSERT IGNORE INTO settings (skey, svalue) VALUES
('phone', '+252 61 000 0000'),
('whatsapp', ''),
('email', 'info@oneoffice.so'),
('address', 'Mogadishu, Somalia'),
('hours', 'Sat – Thu: 8:00 AM – 5:00 PM'),
('facebook', ''),
('twitter', ''),
('instagram', ''),
('linkedin', ''),
('currency', '$');

INSERT INTO settings (skey, svalue) VALUES
('site_name', 'ONE OFFICE'),
('tagline', 'Always at Your Service.'),
('slogan', 'One Office – Many Services'),
('hero_kicker', 'One Stop Government Services Facilitation Center'),
('hero_title', 'Your Government Services Made Simple'),
('hero_text', 'We help you prepare, submit, follow up and collect the services you need from Somali Government institutions – for you, your business and your organization.'),
('about_title', 'One Office – Many Services'),
('about_text', 'ONE OFFICE is a private company with a public-service purpose. We help citizens, businesses and organizations prepare, submit, follow up and collect the services they need from the institutions of the Government of Somalia.'),
('disclaimer', 'ONE OFFICE does not replace government institutions and does not make government decisions – we work as a professional service facilitator.'),
('footer_text', 'One Stop Government Services Facilitation Center. One Office – Many Services.'),
('requests_enabled', '1'),
('request_intro', 'Fill in the form below. You will instantly receive a Tracking Number to follow your request. Our team will contact you to confirm the required documents and the fees.')
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

INSERT INTO categories (id, title, subtitle, description, icon, sort_order) VALUES
(1, 'Companies & Institutions', 'Businesses, NGOs & Organizations', 'Registration, licenses, tax, certification and court documents for companies, NGOs, institutions and traders.', 'bi-buildings', 1),
(2, 'Education', 'Certificates & Academic Documents', 'Issuing, verifying and certifying education certificates and academic documents.', 'bi-mortarboard', 2),
(3, 'Civil Documents', 'Birth, Marriage & Official Records', 'Birth, marriage and death certificates and other official letters and records.', 'bi-person-vcard', 3),
(4, 'Passport & Travel', 'Passport, Visa & Embassy', 'Passport applications, visa documents, embassy services and document legalization.', 'bi-airplane', 4),
(5, 'Diaspora Services', 'For Somalis Living Abroad', 'Services inside Somalia for Somalis living abroad – wherever you are.', 'bi-globe-americas', 5),
(6, 'Business Support & Advisory', 'From Idea to Official Launch', 'From the first idea to the official launch – we help you plan, prepare and grow your project.', 'bi-briefcase', 6);

INSERT INTO services (category_id, icon, title, description, items, sort_order) VALUES
(1, 'bi-building-add', 'Company & business registration', 'Preparing and submitting the documents to register a new company or business.', NULL, 1),
(1, 'bi-file-earmark-check', 'Business licenses', 'Obtaining the business license your company needs.', NULL, 2),
(1, 'bi-arrow-repeat', 'License renewal', 'Renewing your business licenses before they expire.', NULL, 3),
(1, 'bi-receipt', 'Tax Registration', 'Tax registration for companies and businesses.', NULL, 4),
(1, 'bi-patch-check', 'Tax Clearance Certificate', 'Obtaining your Tax Clearance Certificate.', NULL, 5),
(1, 'bi-cash-coin', 'Government tax payments', 'Paying government taxes without standing in queues.', NULL, 6),
(1, 'bi-file-earmark-text', 'Tax receipts & documents', 'Obtaining tax receipts and tax documents.', NULL, 7),
(1, 'bi-clipboard-check', 'Business compliance documents', 'Preparing the compliance documents your business needs.', NULL, 8),
(1, 'bi-shield-check', 'Company document certification', 'Certification and verification of company documents.', NULL, 9),
(1, 'bi-pen', 'Contract certification', 'Certification of contracts and official documents.', NULL, 10),
(1, 'bi-bank2', 'Court documents', 'Obtaining documents from the courts.', NULL, 11),
(1, 'bi-send-check', 'Court document submission & follow-up', 'Submitting court documents and following them up until completion.', NULL, 12),
(1, 'bi-folder-check', 'Institutional documents', 'Registration and renewal of documents for institutions and NGOs.', NULL, 13),
(1, 'bi-truck', 'Document collection & delivery', 'Collecting your documents and delivering them to you.', NULL, 14),

(2, 'bi-award', 'Secondary school certificate', 'Issuing secondary school certificates.', NULL, 1),
(2, 'bi-patch-check', 'Certificate verification', 'Verification of education certificates.', NULL, 2),
(2, 'bi-stamp', 'Education certificate certification', 'Official certification of education certificates.', NULL, 3),
(2, 'bi-journal-text', 'Academic documents', 'Issuing academic documents such as transcripts.', NULL, 4),
(2, 'bi-arrow-counterclockwise', 'Lost certificates', 'Replacement and re-issue of lost certificates.', NULL, 5),
(2, 'bi-clipboard-data', 'Exam results verification', 'Verification of examination results.', NULL, 6),

(3, 'bi-balloon-heart', 'Birth certificate', 'Issuing birth certificates.', NULL, 1),
(3, 'bi-heart', 'Marriage certificate', 'Issuing marriage certificates.', NULL, 2),
(3, 'bi-file-earmark-medical', 'Death certificate', 'Issuing death certificates.', NULL, 3),
(3, 'bi-envelope-paper', 'Official letters & records', 'Obtaining official letters and records.', NULL, 4),
(3, 'bi-shield-check', 'Document verification', 'Verification of civil documents.', NULL, 5),

(4, 'bi-passport', 'Passport applications', 'Help preparing and submitting passport applications.', NULL, 1),
(4, 'bi-file-earmark-person', 'Visa documents', 'Preparing visa application documents.', NULL, 2),
(4, 'bi-flag', 'Embassy services', 'Services related to embassies.', NULL, 3),
(4, 'bi-stamp', 'Certification & legalization', 'Certification and legalization of documents, where applicable.', NULL, 4),

(5, 'bi-award', 'Certificate issuance', 'Issuing certificates while you are abroad.', NULL, 1),
(5, 'bi-patch-check', 'Certificate verification', 'Verification of certificates.', NULL, 2),
(5, 'bi-bank2', 'Court documents', 'Obtaining and following up court documents.', NULL, 3),
(5, 'bi-person-vcard', 'Civil documents', 'Birth, marriage and other civil documents.', NULL, 4),
(5, 'bi-receipt', 'Tax documents', 'Tax documents and receipts.', NULL, 5),
(5, 'bi-building-add', 'Business registration', 'Registering a business in Somalia.', NULL, 6),
(5, 'bi-file-earmark-check', 'Business licenses', 'Obtaining and renewing business licenses.', NULL, 7),
(5, 'bi-envelope-paper', 'Government letters', 'Obtaining official government letters.', NULL, 8),
(5, 'bi-passport', 'Somali Visa', 'Help with Somali Visa applications.', NULL, 9),
(5, 'bi-truck', 'Document delivery', 'Delivering your documents.', NULL, 10),

(6, 'bi-lightbulb', 'Concept Note', 'A short, clear document that explains your idea and project.', 'Define the idea and rationale\nObjectives and scope\nTarget beneficiaries\nExpected impact', 1),
(6, 'bi-journal-richtext', 'Company Profile', 'A professional company profile that represents your organization.', 'Company background\nVision, mission and values\nServices and target markets\nOrganizational structure', 2),
(6, 'bi-easel', 'Professional Presentation', 'A clear presentation for government and partners.', 'Clear and concise slides\nKey information and visuals\nFor government and partners\nShow value and impact', 3),
(6, 'bi-diagram-3', 'Government Service Mapping', 'Mapping government services, their processes and requirements.', 'Identify government services\nMap processes and requirements\nLink to relevant institutions\nCreate a service directory', 4),
(6, 'bi-people', 'Government Relationships & Focal Points', 'Building relationships with government institutions and focal points.', 'Engage key government partners\nIdentify focal points in each ministry\nSign MoUs (where needed)\nEstablish regular coordination', 5),
(6, 'bi-list-check', 'Service SOPs & Pricing', 'Standard operating procedures and transparent pricing.', 'Develop Standard Operating Procedures (SOPs)\nDefine service steps and timelines\nSet transparent and affordable pricing\nEnsure quality and customer service', 6),
(6, 'bi-calculator', 'Startup Budget & Business Plan', 'A startup budget and a complete business plan.', 'Startup costs and resource needs\nFinancial projections\nRevenue model and sustainability\nImplementation plan and milestones', 7),
(6, 'bi-pc-display', 'Office & Staff Setup', 'Setting up the office, equipment and staff.', 'Office location and layout\nEquipment and technology\nRecruit and train staff\nCustomer service standards', 8),
(6, 'bi-clipboard2-pulse', 'Pilot Services', 'Testing services before scaling up.', 'Start with key priority services\nTest processes and systems\nGather feedback from users\nRefine and improve before scaling', 9),
(6, 'bi-rocket-takeoff', 'Official Launch & Expansion', 'The official launch and expansion of services.', 'Official launch event\nPublic awareness and marketing\nScale up services to more sectors\nExpansion to other locations/regions', 10);

INSERT INTO faqs (question, answer, sort_order) VALUES
('Is ONE OFFICE a government agency?', 'No. ONE OFFICE is a private company with a public-service purpose. It does not replace government institutions and does not make government decisions – it works as a professional service facilitator that helps you prepare, submit, follow up and collect your services.', 1),
('How are fees calculated?', 'There are four types: Service Fee (ONE OFFICE''s fee), Government Fee (the official government fee), Delivery Fee and Document Processing Fees. Government fees and ONE OFFICE fees are kept separate, and you receive proof of every payment you make.', 2),
('How can I follow my request?', 'When you submit a request you receive a Tracking Number. On the “Track Request” page, enter the tracking number and your phone number to see the current status.', 3),
('I live abroad – can you help me?', 'Yes. We serve Somalis living abroad who need services inside Somalia: certificates, court and civil documents, tax documents, business registration, government letters, Somali Visa and document delivery.', 4),
('What do I need to get started?', 'Submit a request on the website or visit our office. Our team will tell you which documents are needed for the service you requested and the fees before any work starts.', 5),
('Do you help new businesses or projects?', 'Yes. Our Business Support & Advisory team helps with Concept Notes, Company Profiles, Business Plans, SOPs, office and staff setup, all the way to the official launch.', 6);
