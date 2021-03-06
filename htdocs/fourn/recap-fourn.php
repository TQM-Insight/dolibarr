<?php
/* Copyright (C) 2001-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  	\file       htdocs/fourn/recap-fourn.php
 *		\ingroup    fournisseur
 *		\brief      Page de fiche recap fournisseur
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

$langs->load("companies");
$langs->load("bills");

// Security check
$socid = $_GET["socid"];
if ($user->societe_id > 0)
{
    $action = '';
    $socid = $user->societe_id;
}



/*
 * View
 */

llxHeader();

if ($socid > 0)
{
    $societe = new Societe($db);
    $societe->fetch($socid);

    /*
     * Affichage onglets
     */
    $head = societe_prepare_head($societe);

    dol_fiche_head($head, 'supplier', $langs->trans("ThirdParty"), 0, 'company');


    print "<table width=\"100%\">\n";
    print '<tr><td valign="top" width="50%">';

    print '<table class="border" width="100%">';

    // Nom
    print '<tr><td width="20%">'.$langs->trans("Name").'</td><td width="80%" colspan="3">'.$societe->nom.'</td></tr>';

    // Prefix
    if (! empty($conf->global->SOCIETE_USEPREFIX))  // Old not used prefix field
    {
        print '<tr><td>'.$langs->trans("Prefix").'</td><td colspan="3">';
        print ($societe->prefix_comm?$societe->prefix_comm:'&nbsp;');
        print '</td></tr>';
    }

    print "</table>";

    print "</td></tr></table>\n";

    print '</div>';




    if (! empty($conf->fournisseur->enabled) && $user->rights->facture->lire)
    {
        // Invoices list
        print_fiche_titre($langs->trans("SupplierPreview"));

        print '<table class="noborder" width="100%">';

        $sql = "SELECT s.nom, s.rowid as socid, f.ref_supplier, f.amount, f.datef as df,";
        $sql.= " f.paye as paye, f.fk_statut as statut, f.rowid as facid,";
        $sql.= " u.login, u.rowid as userid";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe as s,".MAIN_DB_PREFIX."facture_fourn as f,".MAIN_DB_PREFIX."user as u";
        $sql.= " WHERE f.fk_soc = s.rowid AND s.rowid = ".$societe->id;
        $sql.= " AND f.fk_user_valid = u.rowid";
        $sql.= " ORDER BY f.datef DESC";

        $resql=$db->query($sql);
        if ($resql)
        {
            $var=true;
            $num = $db->num_rows($resql);

            print '<tr class="liste_titre">';
            print '<td width="100" align="center">'.$langs->trans("Date").'</td>';
            print '<td>&nbsp;</td>';
            print '<td>'.$langs->trans("Status").'</td>';
            print '<td align="right">'.$langs->trans("Debit").'</td>';
            print '<td align="right">'.$langs->trans("Credit").'</td>';
            print '<td align="right">'.$langs->trans("Balance").'</td>';
            print '<td>&nbsp;</td>';
            print '</tr>';

            if (! $num > 0)
            {
                print '<tr><td colspan="7">'.$langs->trans("NoInvoice").'</td></tr>';
            }

            $solde = 0;

            // Boucle sur chaque facture
            for ($i = 0 ; $i < $num ; $i++)
            {
                $objf = $db->fetch_object($resql);

                $fac = new FactureFournisseur($db);
                $ret=$fac->fetch($objf->facid);
                if ($ret < 0)
                {
                    print $fac->error."<br>";
                    continue;
                }
                $totalpaye = $fac->getSommePaiement();

                $var=!$var;
                print "<tr ".$bc[$var].">";

                print "<td align=\"center\">".dol_print_date($fac->date)."</td>\n";
                print "<td><a href=\"facture/card.php?facid=$fac->id\">".img_object($langs->trans("ShowBill"),"bill")." ".$fac->ref."</a></td>\n";

                print '<td aling="left">'.$fac->getLibStatut(2,$totalpaye).'</td>';
                print '<td align="right">'.price($fac->total_ttc)."</td>\n";
                $solde = $solde + $fac->total_ttc;

                print '<td align="right">&nbsp;</td>';
                print '<td align="right">'.price($solde)."</td>\n";

                // Author
                print '<td class="nowrap" width="50"><a href="'.DOL_URL_ROOT.'/user/card.php?id='.$objf->userid.'">'.img_object($langs->trans("ShowUser"),'user').' '.$objf->login.'</a></td>';

                print "</tr>\n";

                // Payments
                $sql = "SELECT p.rowid, p.datep as dp, pf.amount, p.statut,";
                $sql.= " p.fk_user_author, u.login, u.rowid as userid";
                $sql.= " FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn as pf,";
                $sql.= " ".MAIN_DB_PREFIX."paiementfourn as p";
                $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON p.fk_user_author = u.rowid";
                $sql.= " WHERE pf.fk_paiementfourn = p.rowid";
                $sql.= " AND pf.fk_facturefourn = ".$fac->id;

                $resqlp = $db->query($sql);
                if ($resqlp)
                {
                    $nump = $db->num_rows($resqlp);
                    $j = 0;

                    while ($j < $nump)
                    {
                        $objp = $db->fetch_object($resqlp);
                        //$var=!$var;
                        print "<tr ".$bc[$var].">";
                        print '<td align="center">'.dol_print_date($db->jdate($objp->dp))."</td>\n";
                        print '<td>';
                        print '&nbsp; &nbsp; &nbsp; '; // Decalage
                        print '<a href="paiement/card.php?id='.$objp->rowid.'">'.img_object($langs->trans("ShowPayment"),"payment").' '.$langs->trans("Payment").' '.$objp->rowid.'</td>';
                        print "<td>&nbsp;</td>\n";
                        print "<td>&nbsp;</td>\n";
                        print '<td align="right">'.price($objp->amount).'</td>';
                        $solde = $solde - $objp->amount;
                        print '<td align="right">'.price($solde)."</td>\n";

                        // Auteur
                        print '<td class="nowrap" width="50"><a href="'.DOL_URL_ROOT.'/user/card.php?id='.$objp->userid.'">'.img_object($langs->trans("ShowUser"),'user').' '.$objp->login.'</a></td>';

                        print '</tr>';

                        $j++;
                    }

                    $db->free($resqlp);
                }
                else
                {
                    dol_print_error($db);
                }
            }
        }
        else
        {
            dol_print_error($db);
        }
        print "</table>";
        print "<br>";
    }

}
else
{
    dol_print_error($db);
}


$db->close();

llxFooter();
