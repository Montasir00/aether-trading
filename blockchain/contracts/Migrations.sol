// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

/**
 * @title Migrations
 * @dev Required by Truffle to track which migrations have been run on-chain.
 *      This is a standard boilerplate file — do not modify.
 */
contract Migrations {
    address public owner = msg.sender;
    uint public last_completed_migration;

    modifier restricted() {
        require(
            msg.sender == owner,
            "This function is restricted to the contract's owner"
        );
        _;
    }

    function setCompleted(uint completed) public restricted {
        last_completed_migration = completed;
    }
}
