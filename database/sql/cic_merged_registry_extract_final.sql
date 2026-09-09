/* ============================================================================
   CIC MERGED REGISTRY EXTRACT  —  Sky360  (schema-verified 2026-09-09
   against the live  Sky360_OIC_Centralize  DB + "Sky360 Lookup Table.pdf")

   Feeds the member-registry Laravel app.  Returns TWO result sets:
       Result set 1  "Members"  -> members.xlsx  (import as "Members")
       Result set 2  "Loans"    -> loans.xlsx    (import as "Loans")

   ----------------------------------------------------------------------------
   ⚠  THIS DATABASE IS A UNION OF 4 BRANCH DATABASES.  CID and Acc numbers
      REPEAT across branches (CID '000127' is 4 different people).  The real
      key everywhere is  (CID + BR).  So:
        * [Provider Subject No]  = BR + '-' + CID   (globally unique)
        * [Provider Contract No] = BR + '-' + Acc + '-' + Chd
      Every join below is branch-scoped.

   Branches:  000007 Gingoog · 100112 Head Office · 100113 Tagbilaran · 100124 Iligan

   ----------------------------------------------------------------------------
   KEY CORRECTIONS vs the previous draft (all verified against real data):
     * Loans read from T_LNACC (live master, keyed Acc+Chd+BR), NOT T_LNHIST
       (that table only holds the 1,745 CLOSED loans; the old INNER JOIN
       silently dropped ~73 % of loans).
     * Borrower CID from T_RELACC (Type '010' Principal Owner, AppType '4').
     * Delinquency from the amortisation schedule (T_LNINST): T_LNACC.LateDaysNo
       is 0 on EVERY loan in this DB and cannot be used.
     * Contract Phase maps the real 2-char AccStatus (11/12/50/90/99/C1/C2/00);
       the doc's 400..4C2 codes never appear.
     * Purpose of credit = LNCode1 (USERLOOKUP 41), not T_LNRSN (that is 49 =
       loan-proceeds ALLOCATION: insurance, cap build-up…).
     * Date Accepted = T_CIF.RegisterDate (100 % populated) — no more address parsing.
     * Share Capital / Savings balances + last-txn dates from T_SHACC / T_SVACC
       via T_RELACC (010/7 shares, 010/1 savings), branch-scoped.
     * Membership Type / Occupation from T_CIF.CIFCode3 (63) / CIFCode2 (62).
   Assumptions still needing sign-off from ops are tagged  -- ASSUMPTION.
   ============================================================================ */

USE Sky360_OIC_Centralize;   -- change if your DB name differs
SET NOCOUNT ON;

IF OBJECT_ID('tempdb..#Members')      IS NOT NULL DROP TABLE #Members;
IF OBJECT_ID('tempdb..#Addr')         IS NOT NULL DROP TABLE #Addr;
IF OBJECT_ID('tempdb..#Fin')          IS NOT NULL DROP TABLE #Fin;
IF OBJECT_ID('tempdb..#Borrower')     IS NOT NULL DROP TABLE #Borrower;
IF OBJECT_ID('tempdb..#PastDue')      IS NOT NULL DROP TABLE #PastDue;
IF OBJECT_ID('tempdb..#Guar')         IS NOT NULL DROP TABLE #Guar;
IF OBJECT_ID('tempdb..#Link')         IS NOT NULL DROP TABLE #Link;
IF OBJECT_ID('tempdb..#Loans')        IS NOT NULL DROP TABLE #Loans;
IF OBJECT_ID('tempdb..#LoanAggByCID') IS NOT NULL DROP TABLE #LoanAggByCID;

DECLARE @AsOf date = CAST(GETDATE() AS DATE);            -- set to month-end when running historically
DECLARE @Yr12 date = DATEADD(MONTH, -12, CAST(GETDATE() AS DATE));

-- ⚠ VERIFY: Sky360 stores money in CENTAVOS (avg loan "granted" ≈ 13.8M centavos ≈ ₱138k;
--   share balances avg ≈ 1.74M centavos ≈ ₱17k).  @Div = 100 converts to pesos.
--   Set @Div = 1 if your instance already stores pesos.
DECLARE @Div decimal(19,4) = 100.0;

/* ---- helpers: newest address per (CID,BR); one financial-standing row per (CID,BR) ---- */
SELECT a.BR, a.Cid, a.AddressType, a.Line1, a.Line2, a.Line3, a.Line4, a.PostalCode
INTO #Addr
FROM T_ADDRESS a
JOIN (SELECT BR, Cid, AddressType, MAX(Addr_Recid) AS mx
      FROM T_ADDRESS GROUP BY BR, Cid, AddressType) k
  ON k.BR = a.BR AND k.Cid = a.Cid AND k.AddressType = a.AddressType AND k.mx = a.Addr_Recid;

SELECT sh.BR, sh.CID, sh.ShareBal, sh.LastShareTxn, sv.SavBal, sv.LastSavingsTxn
INTO #Fin
FROM (
    SELECT ra.BR, ra.CID, SUM(s.BalAmt) AS ShareBal, MAX(s.CustTrnDate) AS LastShareTxn
    FROM T_RELACC ra
    JOIN T_SHACC s ON s.BR = ra.BR AND s.Acc = ra.ACC AND s.Chd = ra.Chd
    WHERE ra.Type = '010' AND ra.AppType = '7' AND s.AccStatus <> '99'   -- AS 799 = Closed
    GROUP BY ra.BR, ra.CID
) sh
FULL OUTER JOIN (
    SELECT ra.BR, ra.CID, SUM(v.BalAmt) AS SavBal, MAX(v.CustTrnDate) AS LastSavingsTxn
    FROM T_RELACC ra
    JOIN T_SVACC v ON v.BR = ra.BR AND v.Acc = ra.ACC AND v.Chd = ra.Chd
    WHERE ra.Type = '010' AND ra.AppType = '1' AND v.AccStatus <> '99'   -- AS 199 = Closed
    GROUP BY ra.BR, ra.CID
) sv ON sv.BR = sh.BR AND sv.CID = sh.CID;

/* ============================================================================
   PART A  -  #Members   (one row per individual member = one (CID,BR) in T_CIF)
   ============================================================================ */
SELECT
    'ID'                                              AS [Record Type],
    'CO014030'                                        AS [Provider Code],
    br.BrName                                         AS [Branch Code],
    CONVERT(VARCHAR(8), CAST(GETDATE() AS DATE), 112) AS [Subject Reference Date],
    RTRIM(c.BR) + '-' + RTRIM(c.CID)                  AS [Provider Subject No],   -- globally unique
    RTRIM(c.CID)                                      AS [Local CID],
    c.TitleCode                                       AS [Title],
    c.Name2                                           AS [First Name],
    c.Name1                                           AS [Last Name],
    c.Name3                                           AS [Middle Name],
    c.Name4                                           AS [Suffix],
    CAST(NULL AS VARCHAR(50))                         AS [Nickname],
    CAST(NULL AS VARCHAR(50))                         AS [Previous Last Name],
    CASE c.GenderType WHEN '001' THEN 'M' WHEN '002' THEN 'F' END AS [Gender],   -- GT
    c.BirthDate                                       AS [Date of Birth],
    CAST(NULL AS VARCHAR(50))                         AS [Place of Birth],
    CAST(NULL AS VARCHAR(10))                         AS [Country of Birth],
    CAST(NULL AS VARCHAR(10))                         AS [Nationality],
    CAST(NULL AS VARCHAR(5))                          AS [Resident],
    c.CivilStatusCode                                 AS [Civil Status],         -- CS: M00/S00/W00/SEP
    CAST(NULL AS INT)                                 AS [Number of Dependents],
    CAST(NULL AS INT)                                 AS [Car/s Owned],
    sp.Name2                                          AS [Spouse First Name],
    sp.Name1                                          AS [Spouse Last Name],
    sp.Name3                                          AS [Spouse Middle Name],
    CAST(NULL AS VARCHAR(50)) AS [Mother's Maiden First Name],
    CAST(NULL AS VARCHAR(50)) AS [Mother's Maiden Last Name],
    CAST(NULL AS VARCHAR(50)) AS [Mother's Maiden Middle Name],
    CAST(NULL AS VARCHAR(50)) AS [Father First Name],
    CAST(NULL AS VARCHAR(50)) AS [Father Last Name],
    CAST(NULL AS VARCHAR(50)) AS [Father Middle Name],
    CAST(NULL AS VARCHAR(10)) AS [Father Suffix],

    a1.AddressType AS [Address 1: Address Type],
    LTRIM(RTRIM(ISNULL(a1.Line1,'')+' '+ISNULL(a1.Line2,'')+' '+ISNULL(a1.Line3,'')+' '+ISNULL(a1.Line4,''))) AS [Address 1: FullAddress],
    CAST(NULL AS VARCHAR(30)) AS [Address 1: StreetNo],
    a1.PostalCode            AS [Address 1: PostalCode],
    CAST(NULL AS VARCHAR(30)) AS [Address 1: Subdivision],
    CAST(NULL AS VARCHAR(30)) AS [Address 1: Barangay],
    CAST(NULL AS VARCHAR(30)) AS [Address 1: City],
    CAST(NULL AS VARCHAR(30)) AS [Address 1: Province],
    CAST(NULL AS VARCHAR(5))  AS [Address 1: Country],
    CAST(NULL AS VARCHAR(5))  AS [Address 1: House Owner/Lessee],
    CAST(NULL AS DATE)        AS [Address 1: Occupied Since],
    a2.AddressType AS [Address 2: Address Type],
    LTRIM(RTRIM(ISNULL(a2.Line1,'')+' '+ISNULL(a2.Line2,'')+' '+ISNULL(a2.Line3,'')+' '+ISNULL(a2.Line4,''))) AS [Address 2: FullAddress],
    CAST(NULL AS VARCHAR(30)) AS [Address 2: StreetNo],
    a2.PostalCode            AS [Address 2: PostalCode],
    CAST(NULL AS VARCHAR(30)) AS [Address 2: Subdivision],
    CAST(NULL AS VARCHAR(30)) AS [Address 2: Barangay],
    CAST(NULL AS VARCHAR(30)) AS [Address 2: City],
    CAST(NULL AS VARCHAR(30)) AS [Address 2: Province],
    CAST(NULL AS VARCHAR(5))  AS [Address 2: Country],
    CAST(NULL AS VARCHAR(5))  AS [Address 2: House Owner/Lessee],
    CAST(NULL AS DATE)        AS [Address 2: Occupied Since],

    -- Only ~18 % of CIFs carry T_CIF.Nid and several values are junk (kept only if TIN-shaped)
    'NID' AS [Identification 1: Type],
    CASE WHEN c.Nid LIKE '[0-9][0-9][0-9]-[0-9][0-9][0-9]-[0-9][0-9][0-9]%'
              OR c.Nid LIKE '[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]%'
         THEN c.Nid END AS [Identification 1: Number],
    CAST(NULL AS VARCHAR(10)) AS [Identification 2: Type],
    CAST(NULL AS VARCHAR(20)) AS [Identification 2: Number],
    CAST(NULL AS VARCHAR(10)) AS [Identification 3: Type],
    CAST(NULL AS VARCHAR(20)) AS [Identification 3: Number],

    CASE WHEN NULLIF(c.Mobile1,'') IS NOT NULL THEN 'MOBILE' END AS [Contact 1: Type],
    c.Mobile1 AS [Contact 1: Value],
    CASE WHEN NULLIF(c.Email1,'')  IS NOT NULL THEN 'EMAIL'  END AS [Contact 2: Type],
    c.Email1  AS [Contact 2: Value],

    c.Nid AS [Employment: TIN],   -- ORO keeps the TIN in T_CIF.Nid (TaxCode is a code, not a TIN)

    c.CIFCode3 AS [Membership Type Code],                                        -- USERLOOKUP 63
    CASE c.CIFCode3 WHEN '001' THEN 'Regular' WHEN '002' THEN 'Associate'
                    WHEN '003' THEN 'Legal Entity' WHEN '004' THEN 'Micro'
                    WHEN '006' THEN 'Youth/Kiddie' ELSE 'Others' END AS [Membership Type],
    c.CIFCode2 AS [Occupation Category Code],                                     -- USERLOOKUP 62
    CASE c.CIFCode2 WHEN '001' THEN 'Private' WHEN '002' THEN 'Government'
                    WHEN '003' THEN 'Self-employed' WHEN '004' THEN 'Pensioner'
                    WHEN '005' THEN 'Student' WHEN '006' THEN 'Farmer/Fisherfolk'
                    WHEN '007' THEN 'Other' ELSE NULL END AS [Occupation Category],
    c.CIFCode1 AS [Income Bracket Code],                                          -- USERLOOKUP 61

    c.RegisterDate AS [Date Accepted],

    f.ShareBal / @Div  AS [Share Capital Balance],
    f.SavBal   / @Div  AS [Savings Balance],
    f.LastShareTxn     AS [Last Share Transaction Date],
    f.LastSavingsTxn   AS [Last Savings Transaction Date]
INTO #Members
FROM T_CIF c
JOIN T_BRPARMS br ON br.Br = c.BR
LEFT JOIN #Addr a1 ON a1.BR = c.BR AND a1.Cid = c.CID AND a1.AddressType = '001'
LEFT JOIN #Addr a2 ON a2.BR = c.BR AND a2.Cid = c.CID AND a2.AddressType = '002'
LEFT JOIN (SELECT r.BR, r.CID, MIN(r.RelatedCID) AS RelatedCID
           FROM T_RELCID r WHERE r.Type = '061' GROUP BY r.BR, r.CID) rc      -- CC 061 = Spouse
       ON rc.BR = c.BR AND rc.CID = c.CID
LEFT JOIN T_CIF sp ON sp.BR = c.BR AND sp.CID = rc.RelatedCID
LEFT JOIN #Fin f  ON f.BR = c.BR AND f.CID = c.CID
WHERE c.Type = '001';                                                          -- CT 001 = Individual

/* ============================================================================
   PART B  -  #Loans   (one row per active loan = one (Acc,Chd,BR) in T_LNACC)
   ============================================================================ */

-- de-dup T_LNACC to the newest row per (BR,Acc,Chd), then attach the borrower
SELECT la.*
INTO #ActiveLoans
FROM (
    SELECT *, ROW_NUMBER() OVER (PARTITION BY BR, Acc, Chd
                                 ORDER BY AccStatusDate DESC, TrnSeq DESC) AS rn
    FROM T_LNACC
    WHERE AccStatus IN ('11','12','50','90')       -- active + past-maturity ('99' = closed)
) la
WHERE la.rn = 1;

SELECT BR, ACC, Chd, MIN(CID) AS CID
INTO #Borrower
FROM T_RELACC
WHERE Type = '010' AND AppType = '4'              -- Principal Owner of a Loan
GROUP BY BR, ACC, Chd;

-- Past-due amortisations from the schedule (LateDaysNo is dead in this DB).
-- ASSUMPTION: an instalment carrying principal, past due, PaidDate NULL = missed.
SELECT i.BR, i.Acc, i.Chd,
       SUM(CASE WHEN i.PaidDate IS NULL AND i.PriAmt > 0 AND i.DueDate < @AsOf
                THEN i.PriAmt + i.IntAmt ELSE 0 END)  AS OverdueAmt,
       SUM(CASE WHEN i.PaidDate IS NULL AND i.PriAmt > 0 AND i.DueDate < @AsOf
                THEN 1 ELSE 0 END)                    AS OverdueCnt,
       SUM(CASE WHEN i.PaidDate IS NULL AND i.PriAmt > 0
                 AND i.DueDate < @AsOf AND i.DueDate >= @Yr12
                THEN 1 ELSE 0 END)                    AS OverdueLast12Mo,
       MIN(CASE WHEN i.PaidDate IS NULL AND i.PriAmt > 0 AND i.DueDate < @AsOf
                THEN i.DueDate END)                   AS OldestUnpaidDue
INTO #PastDue
FROM T_LNINST i
WHERE i.Status IN ('1','9')
GROUP BY i.BR, i.Acc, i.Chd;

SELECT ra.BR, ra.ACC, ra.Chd, ra.CID AS GCID, g.Name1, g.Name2,
       ROW_NUMBER() OVER (PARTITION BY ra.BR, ra.ACC, ra.Chd ORDER BY ra.CID) AS n
INTO #Guar
FROM T_RELACC ra JOIN T_CIF g ON g.BR = ra.BR AND g.CID = ra.CID
WHERE ra.Type = '030' AND ra.AppType = '4';

SELECT ra.BR, ra.ACC, ra.Chd, ra.CID AS LCID, ra.Type AS RelType, l.Name1, l.Name2,
       ROW_NUMBER() OVER (PARTITION BY ra.BR, ra.ACC, ra.Chd ORDER BY ra.CID) AS n
INTO #Link
FROM T_RELACC ra JOIN T_CIF l ON l.BR = ra.BR AND l.CID = ra.CID
WHERE ra.Type IN ('011','013','014') AND ra.AppType = '4';

SELECT
    'CI'                                              AS [Record Type],
    'CO014030'                                        AS [Provider Code],
    brn.BrName                                        AS [Branch Code],
    CONVERT(VARCHAR(8), CAST(GETDATE() AS DATE), 112) AS [Contract Reference Date],
    RTRIM(la.BR) + '-' + RTRIM(b.CID)                 AS [Provider Subject No],
    'B'                                               AS [Role],
    RTRIM(la.BR) + '-' + RTRIM(la.Acc) + '-' + RTRIM(la.Chd) AS [Provider Contract No],
    la.LNCode1                                        AS [Contract Type],           -- USERLOOKUP 41
    CASE la.AccStatus
        WHEN '00' THEN 'PE' WHEN '01' THEN 'PE'
        WHEN '11' THEN 'AC' WHEN '50' THEN 'AC' WHEN '12' THEN 'AC'
        WHEN '90' THEN 'PM' WHEN '99' THEN 'CL'
        WHEN 'C1' THEN 'CA' WHEN 'C2' THEN 'CA' ELSE la.AccStatus
    END                                              AS [Contract Phase],
    la.AccStatus                                      AS [Contract Status],
    ISNULL(ccy.Code, la.CcyType)                      AS [Currency],
    la.CcyType                                        AS [Original Currency],
    la.OpenDate                                       AS [Contract Start Date],
    CAST(NULL AS DATE)                                AS [Contract Request Date],
    la.MatDate                                        AS [Contract End Planned Date],
    CASE WHEN la.AccStatus = '99' THEN la.AccStatusDate END AS [Contract End Actual Date],
    la.LastTrnDate                                    AS [Last Payment Date],       -- ASSUMPTION: last account movement
    0                                                 AS [Reorganized Credit Code],
    0                                                 AS [Board Resolution flag],
    ISNULL(la.GrantedAmtOrig, la.GrantedAmt) / @Div   AS [Financed Amount],
    la.InstNo                                         AS [Installments Number],
    'NA'                                              AS [Transaction Type / Sub-facility],
    la.LNCode1                                        AS [Purpose of credit],       -- USERLOOKUP 41
    CASE la.FreqType
        WHEN '000' THEN 'NEVER' WHEN '001' THEN 'A' WHEN '002' THEN 'SA'
        WHEN '004' THEN 'Q'  WHEN '006' THEN 'BM' WHEN '012' THEN 'M'
        WHEN '024' THEN 'SM' WHEN '026' THEN 'F'  WHEN '052' THEN 'W'
        WHEN '360' THEN 'D'  ELSE la.FreqType
    END                                              AS [Payment Periodicity],
    CAST(NULL AS VARCHAR(5))                          AS [Payment Method],
    la.FixAmt / @Div                                 AS [Monthly Payment Amount],
    la.FirstInstDueDate                              AS [First Payment Date],
    CAST(NULL AS DECIMAL(18,2))                       AS [Last payment amount],
    ni.NextDue                                        AS [Next Payment Date],
    la.FixAmt / @Div                                  AS [Next Payment],
    la.UnExInstNo                                     AS [Outstanding Payments Number],
    ISNULL(la.BalAmt, 0)    / @Div                    AS [Outstanding Balance],
    ISNULL(la.IntBalAmt, 0) / @Div                    AS [Interest Balance],
    ISNULL(la.PenBalAmt, 0) / @Div                    AS [Penalty Balance],
    ISNULL(pd.OverdueCnt, 0)                          AS [Overdue Payments Number],
    CASE WHEN ISNULL(pd.OverdueAmt,0) > 0 THEN pd.OverdueAmt / @Div ELSE 0 END AS [Overdue Payments Amount],
    ISNULL(pd.OverdueLast12Mo, 0)                     AS [Overdue Installments Last 12 Months],
    CASE WHEN pd.OldestUnpaidDue IS NOT NULL THEN DATEDIFF(DAY, pd.OldestUnpaidDue, @AsOf) ELSE 0 END AS [Overdue Days],
    CAST(NULL AS VARCHAR(5))  AS [Good Type],
    CAST(NULL AS VARCHAR(30)) AS [Good Value],
    CAST(NULL AS VARCHAR(1))  AS [New/Used Code],
    CAST(NULL AS VARCHAR(30)) AS [Good Brand],
    CAST(NULL AS DATE)        AS [Manufacturing Date],
    CAST(NULL AS VARCHAR(30)) AS [Registration number],
    g1.GCID AS [Provider Guarantee No 1], g1.GCID AS [Provider Subject No (Guarantor) 1],
    LTRIM(RTRIM(ISNULL(g1.Name2,'')+' '+ISNULL(g1.Name1,''))) AS [Guarantor Name 1],
    g2.GCID AS [Provider Guarantee No 2], g2.GCID AS [Provider Subject No (Guarantor) 2],
    LTRIM(RTRIM(ISNULL(g2.Name2,'')+' '+ISNULL(g2.Name1,''))) AS [Guarantor Name 2],
    g3.GCID AS [Provider Guarantee No 3], g3.GCID AS [Provider Subject No (Guarantor) 3],
    LTRIM(RTRIM(ISNULL(g3.Name2,'')+' '+ISNULL(g3.Name1,''))) AS [Guarantor Name 3],
    ls1.LCID AS [Provider Subject No (Linked Subject 1)], ls1.RelType AS [Linked Subject 1 Role],
    LTRIM(RTRIM(ISNULL(ls1.Name2,'')+' '+ISNULL(ls1.Name1,''))) AS [Name of the Linked Subject 1],
    ls2.LCID AS [Provider Subject No (Linked Subject 2)], ls2.RelType AS [Linked Subject 2 Role],
    LTRIM(RTRIM(ISNULL(ls2.Name2,'')+' '+ISNULL(ls2.Name1,''))) AS [Name of the Linked Subject 2]
INTO #Loans
FROM #ActiveLoans la
JOIN #Borrower b       ON b.BR = la.BR AND b.ACC = la.Acc AND b.Chd = la.Chd
LEFT JOIN T_CIF cif    ON cif.BR = la.BR AND cif.CID = b.CID
LEFT JOIN T_BRPARMS brn ON brn.Br = la.BR
LEFT JOIN CCY ccy      ON ccy.Code = la.CcyType
LEFT JOIN #PastDue pd  ON pd.BR = la.BR AND pd.Acc = la.Acc AND pd.Chd = la.Chd
OUTER APPLY (
    SELECT MIN(x.DueDate) AS NextDue
    FROM T_LNINST x
    WHERE x.BR = la.BR AND x.Acc = la.Acc AND x.Chd = la.Chd
      AND x.PaidDate IS NULL AND x.DueDate >= @AsOf
) ni
LEFT JOIN #Guar g1 ON g1.BR = la.BR AND g1.ACC = la.Acc AND g1.Chd = la.Chd AND g1.n = 1
LEFT JOIN #Guar g2 ON g2.BR = la.BR AND g2.ACC = la.Acc AND g2.Chd = la.Chd AND g2.n = 2
LEFT JOIN #Guar g3 ON g3.BR = la.BR AND g3.ACC = la.Acc AND g3.Chd = la.Chd AND g3.n = 3
LEFT JOIN #Link ls1 ON ls1.BR = la.BR AND ls1.ACC = la.Acc AND ls1.Chd = la.Chd AND ls1.n = 1
LEFT JOIN #Link ls2 ON ls2.BR = la.BR AND ls2.ACC = la.Acc AND ls2.Chd = la.Chd AND ls2.n = 2;

/* ============================================================================
   PART C  -  per-member loan roll-up
   ============================================================================ */
SELECT
    l.[Provider Subject No]                               AS CID,
    COUNT(*)                                              AS LoanCount,
    SUM(l.[Financed Amount])                              AS TotalFinancedAmount,
    SUM(l.[Outstanding Balance])                          AS TotalOutstandingBalance,
    SUM(l.[Overdue Payments Amount])                      AS TotalOverdueAmount,
    SUM(l.[Overdue Installments Last 12 Months])          AS OverdueInstallmentsLast12Mo,
    MAX(l.[Overdue Days])                                 AS MaxOverdueDays,
    SUM(CASE WHEN l.[Overdue Payments Amount] > 0 OR l.[Overdue Days] > 0 THEN 1 ELSE 0 END) AS DelinquentLoanCount,
    MIN(l.[Contract Start Date])                          AS EarliestLoanOpenDate,
    MAX(l.[Contract End Planned Date])                    AS LatestLoanMaturityDate
INTO #LoanAggByCID
FROM #Loans l
GROUP BY l.[Provider Subject No];

/* ============================================================================
   RESULT SET 1  -  "Members"
   ============================================================================ */
SELECT
    m.*,
    ISNULL(la.LoanCount, 0)                   AS [Loan Count],
    ISNULL(la.TotalFinancedAmount, 0)         AS [Total Financed Amount],
    ISNULL(la.TotalOutstandingBalance, 0)     AS [Total Outstanding Balance],
    ISNULL(la.TotalOverdueAmount, 0)          AS [Total Overdue Amount],
    ISNULL(la.OverdueInstallmentsLast12Mo, 0) AS [Overdue Installments Last 12 Months],
    ISNULL(la.MaxOverdueDays, 0)              AS [Max Overdue Days],
    ISNULL(la.DelinquentLoanCount, 0)         AS [Delinquent Loan Count],
    la.EarliestLoanOpenDate                   AS [Earliest Loan Open Date],
    la.LatestLoanMaturityDate                 AS [Latest Loan Maturity Date]
FROM #Members m
LEFT JOIN #LoanAggByCID la ON la.CID = m.[Provider Subject No]
ORDER BY m.[Branch Code], m.[Last Name], m.[First Name];

/* ============================================================================
   RESULT SET 2  -  "Loans"
   ============================================================================ */
SELECT * FROM #Loans
ORDER BY [Provider Subject No], [Provider Contract No];

IF OBJECT_ID('tempdb..#ActiveLoans') IS NOT NULL DROP TABLE #ActiveLoans;
