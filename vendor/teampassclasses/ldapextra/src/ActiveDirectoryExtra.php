<?php

namespace TeampassClasses\LdapExtra;

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      ActiveDirectoryExtra.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Models\ActiveDirectory\Group as BaseGroup ;
use LdapRecord\Models\ActiveDirectory\User as AdUser;
use LdapRecord\Connection;
use LdapRecord\Container;

class ActiveDirectoryExtra extends BaseGroup 
{
    public function getADGroups(Connection $connection, array $settings): array
    {
        // Configure objectClasses based on settings
        if (isset($settings['ldap_group_objectclasses_attibute']) === true) {
            static::$objectClasses = explode(",",$settings['ldap_group_objectclasses_attibute']);
        }
        if (!$connection || !$connection->isConnected()) {
            return [
                'error' => true,
                'message' => 'No valid LDAP connection is available for the query.',
                'userGroups' => [],
            ];
        }
        // prepare query
        $query = $connection->query()
            ->rawfilter($settings['ldap_group_object_filter']);

        // get all parameters to search
        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }
        try {
            // perform query and get data
            $groups = $query->get();

            $groupsArr = [];
            foreach($groups as $key => $group) {
                $adGroupId = (int) $group[(isset($settings['ldap_guid_attibute']) === true && empty($settings['ldap_guid_attibute']) === false ? $settings['ldap_guid_attibute'] : 'gidnumber')][0];
                $groupsArr[$adGroupId] = [
                    'ad_group_id' => $adGroupId,
                    'ad_group_title' => $group['cn'][0],
                    'role_id' => -1,
                    'id' => -1,
                    'role_title' => '',
                ];
            }            

            return [
                'error' => false,
                'message' => 'Groups fetched successfully.',
                'userGroups' => $groupsArr,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'LDAP Error: ' . $e->getMessage(),
                'userGroups' => [],
            ];
        }
    }


    function getUserADGroups(string $userDN, Connection $connection, array $SETTINGS): array
    {
        // init
        $groupsArr = [];

        try {
            Container::addConnection($connection);

            // Check if connection is ok
            if (!$connection->isConnected()) {
                $connection->connect();
            }

            // get id attribute
            if (isset($SETTINGS['ldap_guid_attibute']) ===true && empty($SETTINGS['ldap_guid_attibute']) === false) {
                $idAttribute = $SETTINGS['ldap_guid_attibute'];
            } else {
                $idAttribute = 'objectguid';
            }

            // Get user groups from AD
            $user = AdUser::find($userDN);
            $groups = $user->groups()->get();
            foreach ($groups as $group) {
                array_push(
                    $groupsArr,
                    $group[$idAttribute][0]
                );
            }
        } catch (\LdapRecord\Auth\BindException $e) {
            error_log("TEAMPASS ERROR - ".__FILE__." - userIsEnabled - ".$e->getMessage());
        }

        return [
            'error' => false,
            'message' => '',
            'userGroups' => $groupsArr,
        ];
    }

    /**
     * Check is user is enabled
     *
     * @param string $userDN
     * @param Connection $connection
     * @return bool
     */
    function userIsEnabled(string $userDN, Connection $connection): bool
    {
        $isEnabled = false;
        try {
            Container::addConnection($connection);

            // Check if connection is ok
            if (!$connection->isConnected()) {
                $connection->connect();
            }

            $user = AdUser::find($userDN);
            if (!$user) {
                return false;
            }
            $isEnabled = $user->isEnabled();
        } catch (\LdapRecord\Auth\BindException $e) {
            error_log("TEAMPASS ERROR - ".__FILE__." - userIsEnabled - ".$e->getMessage());
        }
        return $isEnabled;
    }
}
