CREATE EXTENSION pgcrypto;

CREATE TABLE piatto (
    piatto_id SERIAL PRIMARY KEY,
    titolo VARCHAR(100) UNIQUE NOT NULL,
    descrizione TEXT NOT NULL,
    foto VARCHAR(200) NOT NULL,
    tempo_preparazione SMALLINT NOT NULL,
    complessita SMALLINT NOT NULL CHECK (complessita >= 1 AND complessita <= 5)
);

INSERT INTO  piatto(piatto_id, titolo, descrizione, foto, tempo_preparazione, complessita)
    VALUES
        (10, 'Hamburger', 'Hamburger con carne bovina, cipolla tritata e formaggio', 'hamburger.jpg', 10, 1),
        (5, 'Trofie al pesto', 'Pasta tradizionale ligure con pesto di basilico', 'trofie-al-pesto.jpg', 15, 2),
        (11, 'Penne panna e prosciutto', 'Pasta con panna e prosciutto', 'pasta-prosciutto.jpg', 10, 3),
        (12, 'Spaghetti alla carbonara', 'Pasta con uovo, guanciale e pecorino', 'spaghetti-carbonara.jpg', 20, 4);

------

CREATE TABLE piatto_giorno (
    piatto INT NOT NULL REFERENCES piatto(piatto_id)
        ON DELETE NO ACTION
        ON UPDATE CASCADE,
    giorno DATE NOT NULL,
    quantita SMALLINT NOT NULL CHECK (quantita >= 0),
    prezzo NUMERIC(5, 2) NOT NULL CHECK (prezzo > 0),
    PRIMARY KEY (piatto, giorno)
);

INSERT INTO piatto_giorno(piatto, giorno, quantita, prezzo)
    VALUES (5, '2017-01-19', 8, 7.99),
        (10, '2017-01-19', 12, 4.99),
        (11, '2017-01-19', 7, 8.99),
        (5, '2017-01-21', 6, 7.99),
        (11, '2017-01-21', 6, 8.99),
        (10, '2017-01-21', 8, 4.99);

------

CREATE DOMAIN dom_utente_ruolo AS VARCHAR(11) NOT NULL DEFAULT 'utente' CHECK (VALUE IN ('utente', 'ristoratore'));

CREATE TABLE utente (
    utente_id SERIAL PRIMARY KEY,
    email VARCHAR(50) UNIQUE NOT NULL CHECK (char_length(email) >= 3),
    password_hash VARCHAR(100) NOT NULL,
    ruolo dom_utente_ruolo
);

INSERT INTO utente(utente_id, email, password_hash, ruolo)
    VALUES (5,'utente@mail.com', '$2a$10$9AEK723LTyFsilYzsRw6Guoeb37QonTu04KA8Sk9n3KnOyCNHlCku', 'utente'),
        (6, 'ristoratore@mail.com', '$2a$10$yi2iml4HRP3dVhMW.CYen.kD9nxRW556qtJhPcTmLMmlfZQtp2BIy', 'ristoratore');

------

CREATE TABLE area_consegna (
    cap VARCHAR(5) PRIMARY KEY CHECK (char_length(cap) = 5)
);

INSERT INTO area_consegna(cap)
    VALUES ('20141'), ('20143'), ('20146'), ('20148'), ('20152');

------

CREATE TABLE indirizzo (
    via VARCHAR(50) PRIMARY KEY,
    cap VARCHAR(5) NOT NULL REFERENCES area_consegna(cap)
        ON DELETE NO ACTION
        ON UPDATE CASCADE
);

INSERT INTO indirizzo(via, cap)
    VALUES ('via falsa', '20143'), ('via vera', '20152');

------

CREATE TABLE lavoratore (
    lavoratore_id SERIAL PRIMARY KEY,
    nome VARCHAR(30) NOT NULL,
    cognome VARCHAR(30) NOT NULL
);

INSERT INTO lavoratore(lavoratore_id, nome, cognome)
    VALUES (1, 'Marco', 'Verdi'), (2, 'Luca', 'Bianchi'), (3, 'Giorgio', 'Neri'), (4, 'Silvia', 'Neri');

------

CREATE TABLE linea_preparazione (
    lavoratore INT NOT NULL REFERENCES lavoratore(lavoratore_id),
    giorno DATE NOT NULL,
    PRIMARY KEY (lavoratore, giorno)
);

INSERT INTO linea_preparazione(lavoratore, giorno)
    VALUES (1, '2017-01-19'), (2, '2017-01-19'), (3, '2017-01-19'), (1, '2017-01-21'), (2, '2017-01-21');

------

CREATE TABLE ordine (
    ordine_id SERIAL PRIMARY KEY,
    utente INT NOT NULL REFERENCES utente(utente_id)
        ON DELETE NO ACTION
        ON UPDATE CASCADE,
    istante_ordine TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nome VARCHAR(30) NOT NULL,
    cognome VARCHAR(30) NOT NULL,
    telefono VARCHAR(10) NOT NULL,
    ora_consegna TIME,
    indirizzo VARCHAR(50) NOT NULL REFERENCES indirizzo(via)
);

INSERT INTO ordine(ordine_id, utente, istante_ordine, nome, cognome, telefono, ora_consegna, indirizzo)
    VALUES (111, 6, '2017-01-21 17:16:44.238898', 'Marco', 'Rossi', '3654875921', '18:00:00', 'via falsa'),
        (113, 5, '2017-01-21 17:36:42.175416', 'Francesca', 'Franceschi', '3298754258', NULL, 'via vera');

------

CREATE TABLE preparazione (
    ordine INT NOT NULL REFERENCES ordine(ordine_id)
        ON DELETE NO ACTION
        ON UPDATE CASCADE,
    piatto INT NOT NULL,
    linea INT NOT NULL,
    giorno DATE NOT NULL,
    progressivo_piatto SMALLINT NOT NULL,
    fine_preparazione TIMESTAMP NOT NULL,
    PRIMARY KEY (ordine, piatto, linea, giorno, progressivo_piatto),
    FOREIGN KEY (piatto, giorno) REFERENCES piatto_giorno(piatto, giorno),
    FOREIGN KEY (linea, giorno) REFERENCES linea_preparazione(lavoratore, giorno)
);

INSERT INTO preparazione(ordine, piatto, linea, giorno, progressivo_piatto, fine_preparazione)
    VALUES (111, 5, 1, '2017-01-21', 1, '2017-01-21 17:31:44.238898'),
        (111, 5, 2, '2017-01-21', 2, '2017-01-21 17:31:44.238898'),
        (111, 10, 1, '2017-01-21', 1, '2017-01-21 17:36:44.238898'),
        (111, 10, 2, '2017-01-21', 2, '2017-01-21 17:36:44.238898'),
        (111, 10, 1, '2017-01-21', 3, '2017-01-21 17:41:44.238898'),
        (111, 11, 2, '2017-01-21', 1, '2017-01-21 17:46:44.238898'),
        (113, 10, 1, '2017-01-21', 1, '2017-01-21 17:47:42.175416');

------

CREATE TABLE fattura (
    ordine INT NOT NULL REFERENCES ordine(ordine_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    codice_fiscale VARCHAR(16) NOT NULL,
    partita_iva VARCHAR(11),
    societa VARCHAR(50),
    indirizzo_fiscale VARCHAR(50),
    PRIMARY KEY (ordine, codice_fiscale)
);

------

CREATE VIEW statistiche_linea AS
    SELECT P.giorno, P.linea, COUNT(*) AS num_piatti, SUM(D.tempo_preparazione) AS tempo_lavoro,
        COUNT(DISTINCT P.ordine) AS clienti_serviti, cast(SUM(PG.prezzo) / COUNT(*) AS NUMERIC(5, 2)) AS val_medio_piatti
    FROM preparazione P
        JOIN piatto D ON P.piatto = D.piatto_id
        JOIN piatto_giorno PG ON P.piatto = PG.piatto AND P.giorno = PG.giorno
    GROUP BY P.linea, P.giorno
    ORDER BY P.giorno ASC, P.linea ASC;

------

CREATE MATERIALIZED VIEW coda_preparazione AS
    SELECT P.ordine, P.piatto, P.linea, D.tempo_preparazione, O.istante_ordine, P.fine_preparazione
    FROM preparazione P
        JOIN piatto D ON P.piatto = D.piatto_id
        JOIN ordine O ON P.ordine = O.ordine_id
    WHERE P.fine_preparazione > CURRENT_TIMESTAMP
    ORDER BY P.fine_preparazione ASC;


-- Trigger fine_preparazione
-- Calcola il TIMESTAMP della fine della preparazione di un piatto sulla base della coda di
-- preparazione e del tempo richiesto per prepararlo (a partire dall'istante in cui è stato
-- effettuato l'ordine), e lo memorizza nell'attributo `preparazione.fine_preparazione`
CREATE OR REPLACE FUNCTION calcola_fine_preparazione() RETURNS TRIGGER AS $$
BEGIN
    NEW.fine_preparazione = (
        SELECT istante_ordine FROM ordine WHERE ordine_id = NEW.ordine
    ) + INTERVAL '1' minute * (
        carico_linea(NEW.linea) + (
            SELECT tempo_preparazione FROM piatto WHERE piatto_id = NEW.piatto
        )
    );

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER fine_preparazione BEFORE INSERT ON preparazione FOR EACH ROW EXECUTE PROCEDURE calcola_fine_preparazione();


-- Trigger aggiorna_coda
-- Aggiorna la coda di preparazione ad ogni piatto ordinato/annullato
CREATE OR REPLACE FUNCTION aggiorna_coda_preparazione() RETURNS TRIGGER AS $$
BEGIN
    REFRESH MATERIALIZED VIEW coda_preparazione;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

CREATE TRIGGER aggiorna_coda AFTER INSERT OR DELETE ON preparazione FOR EACH ROW EXECUTE PROCEDURE aggiorna_coda_preparazione();


-- Data una linea di preparazione restituisce il suo carico di lavoro, ossia i minuti necessari affinchè si liberi
-- Input:  id linea preparazione
-- Output: minuti necessari per completare i piatti assegnati
CREATE OR REPLACE FUNCTION carico_linea(line INT) RETURNS INT AS $$
DECLARE
    t INT;
BEGIN
    -- aggiorna la coda di preparazione per evitare di conteggiare il tempo di preparazioni concluse
    REFRESH MATERIALIZED VIEW coda_preparazione;

    SELECT ceil(extract(EPOCH FROM (MAX(fine_preparazione) - CURRENT_TIMESTAMP)) / 60) INTO t FROM coda_preparazione
    WHERE linea = line;

    IF t IS NULL THEN
        t := 0;
    END IF;

    RETURN t;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;


-- Restituisce la linea di preparazione con meno lavoro, a cui assegnare il prossimo piatto da preparare
-- Input:  --
-- Output: Id della linea meno carica
CREATE OR REPLACE FUNCTION linea_meno_carica() RETURNS INT AS $$
DECLARE
    id linea_preparazione.lavoratore%TYPE;
    id_tmp linea_preparazione.lavoratore%TYPE;
BEGIN
    -- aggiorna la coda di preparazione per evitare di considerare preparazioni concluse
    REFRESH MATERIALIZED VIEW coda_preparazione;

    SELECT linea INTO id FROM coda_preparazione
    GROUP BY linea HAVING SUM(tempo_preparazione) <= ALL (
        SELECT SUM(tempo_preparazione) FROM coda_preparazione GROUP BY linea
    ) LIMIT 1;

    -- se la coda è vuota recupera una linea a caso
    IF id IS NULL THEN
        SELECT lavoratore INTO id FROM linea_preparazione WHERE giorno = CURRENT_DATE LIMIT 1;
    -- se non è vuota verifica che non ci siano linee senza lavoro
    ELSE
        SELECT lavoratore INTO id_tmp FROM linea_preparazione
        WHERE giorno = CURRENT_DATE AND lavoratore NOT IN (
            SELECT DISTINCT linea FROM coda_preparazione
        ) LIMIT 1;

        IF id_tmp IS NOT NULL THEN
            id := id_tmp;
        END IF;
    END IF;

    RETURN id;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;


-- Trigger piatti_dispobinili
-- Aggiorna la disponibilità dei piatti in base agli ordini ricevuti o annullati
CREATE OR REPLACE FUNCTION aggiorna_piatti_disponibili() RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE piatto_giorno SET quantita = quantita - 1
        WHERE piatto = NEW.piatto AND giorno = NEW.giorno;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE piatto_giorno SET quantita = quantita + 1
        WHERE piatto = OLD.piatto AND giorno = OLD.giorno;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER piatti_disponibili AFTER INSERT OR DELETE ON preparazione FOR EACH ROW EXECUTE PROCEDURE aggiorna_piatti_disponibili();


-- Calcola l'ora di consegna dell'ordine sulla base del tempo di preparazione dei piatti (tenuto
-- conto anche di eventuali altri ordini in coda) e della zona di consegna.
-- Il tempo di percorrenza dal ristorante all'utente è calcolato come:
--     min_base + |(rist_cap - user_cap)| * min_per_cap
-- NB: si assume che i CAP degli utenti siano in un intorno del CAP del ristorante, altrimenti
--     il calcolo può portare a valori insensati
-- Input:  id ordine
-- Output: timestamp dell'ora di consegna
CREATE OR REPLACE FUNCTION calcola_ora_consegna(order_id INT) RETURNS TIMESTAMP AS $$
DECLARE
    rist_cap area_consegna.cap%TYPE := '20145'; -- CAP del ristorante
    user_cap area_consegna.cap%TYPE; -- CAP dove consegnare l'ordine
    min_per_cap INT := 1; -- minuti necessari per percorrere la strada tra due CAP consecutivi
    min_base INT := 5; -- tempo minimo di consegna
    fine_prep TIMESTAMP; -- timestamp della fine della preparazione dell'ordine
BEGIN
    REFRESH MATERIALIZED VIEW coda_preparazione;

    SELECT MAX(fine_preparazione) INTO fine_prep FROM coda_preparazione WHERE ordine = order_id;

    -- SELECT MAX(fine_preparazione) INTO fine_prep FROM preparazione WHERE ordine = order_id;

    SELECT cap INTO user_cap FROM ordine O
        JOIN indirizzo I ON O.indirizzo = I.via
    WHERE ordine_id = order_id;

    RETURN fine_prep + INTERVAL '1' minute * (min_base + abs(cast(rist_cap as INTEGER) - cast(user_cap as INTEGER)) * min_per_cap);
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;



-- REGISTRAZIONE e AUTENTICAZIONE
---------------------------------------------------------------------------------------------------

-- Dominio da usare per verificare che le password plaintext passate alle funzioni di hashing non siano stringhe vuote.
CREATE DOMAIN dom_password_plain VARCHAR(72) NOT NULL CHECK (char_length(VALUE) > 0);

-- Date un'email e una password crea il corrispettivo utente.
-- Input:  email, password, ruolo (facoltativo)
-- Output: --
CREATE OR REPLACE FUNCTION crea_utente(email VARCHAR(50), password dom_password_plain, role dom_utente_ruolo DEFAULT 'utente') RETURNS VOID AS $$
BEGIN
    INSERT INTO utente(email, password_hash, ruolo)
        VALUES (email, crypt(password, gen_salt('bf', 10)), role);
END;
$$ LANGUAGE plpgsql;

-- Data un'email e una password restituisce l'utente corrispondente.
-- Input:  email, password
-- Output: la tupla di `utente` che corrisponde alle credenziali passate
CREATE OR REPLACE FUNCTION autentica(email_p VARCHAR(50), password dom_password_plain) RETURNS utente AS $$
DECLARE
    u RECORD;
BEGIN
    SELECT * INTO u
    FROM utente
    WHERE email = email_p AND password_hash = crypt(password, password_hash);

    RETURN u;
END;
$$ LANGUAGE plpgsql;

-- Dato un utente ne aggiorna la password dopo averne verificato l'identità.
-- Input:  id utente, vecchia password (per verifica), nuova password
-- Output: id dell'utente coninvolto dall'aggiornamento
CREATE OR REPLACE FUNCTION modifica_password(id INT, old_password dom_password_plain, new_password dom_password_plain) RETURNS INT AS $$
DECLARE
    affected_id utente.utente_id%TYPE;
BEGIN
    UPDATE utente
        SET password_hash = crypt(new_password, gen_salt('bf', 10))
    WHERE utente_id = id AND password_hash = crypt(old_password, password_hash)
        RETURNING utente_id INTO affected_id;

    RETURN affected_id;
END;
$$ LANGUAGE plpgsql;



-- RUOLI e PERMESSI
---------------------------------------------------------------------------------------------------

-- limita i permessi DDL al solo superuser
REVOKE CREATE ON SCHEMA public FROM PUBLIC;

-- il ristoratore ha permessi totali DML su tutti gli oggetti del db
CREATE ROLE role_ristoratore WITH LOGIN PASSWORD 'md59022bae16b1f21f7834389cbcdb6b98d';
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO role_ristoratore;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO role_ristoratore;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO role_ristoratore;

-- l'utente è un ristoratore senza permessi per gestire la parte amministrativa del ristorante (piatti, aree consegna, ecc...)
CREATE ROLE role_utente WITH LOGIN PASSWORD 'md53540a17ce4d70bafa108f5553e782bea';
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO role_utente;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO role_utente;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO role_utente;
REVOKE INSERT, UPDATE, DELETE ON piatto, piatto_giorno, area_consegna, lavoratore, linea_preparazione FROM role_utente;
REVOKE ALL ON statistiche_linea FROM role_utente;
